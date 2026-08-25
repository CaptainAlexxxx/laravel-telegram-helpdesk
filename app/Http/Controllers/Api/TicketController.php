<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotLog;
use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected TelegramService $telegramService
    ) {}

    /**
     * Get list of tickets
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['user', 'firstAgent', 'lastAgent'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->to);
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 20) ?: 20, 100);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get single ticket with history
     */
    public function show(int $id): JsonResponse
    {
        $ticket = Ticket::with(['user', 'firstAgent', 'lastAgent'])
            ->find($id);

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Get ticket history (logs)
        $history = BotLog::where('ticket_id', $id)
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $ticket,
                'history' => $history,
            ],
        ]);
    }

    /**
     * Send message from agent to client
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'required|integer',
            'message' => 'required|string|max:4096',
        ]);

        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        if ($ticket->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is closed',
            ], 400);
        }

        $agent = TelegramUser::find($request->agent_id);

        if (! $agent || ! $agent->isAgent()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid agent',
            ], 400);
        }

        try {
            // Send message to client via bot
            $bot = app(Nutgram::class);
            $bot->sendMessage(
                text: $request->message,
                chat_id: $ticket->user->telegram_id,
                parse_mode: 'HTML'
            );

            // Update ticket metrics
            if (! $ticket->first_reply_at) {
                $this->ticketService->addFirstReply($ticket, $agent->id);
            } else {
                $this->ticketService->updateLastAgent($ticket, $agent->id);
            }

            $this->telegramService->resetAgentWaitingTimer($ticket);

            // Log message
            BotLog::logEvent(
                eventType: 'message',
                eventSource: 'web',
                userId: $ticket->user_id,
                ticketId: $ticket->id,
                payload: [
                    'agent_id' => $agent->id,
                    'agent_name' => $agent->full_name,
                    'text' => $request->message,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send agent message', ['ticket_id' => $ticket->id, 'exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
            ], 500);
        }
    }

    /**
     * Close ticket
     */
    public function close(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'agent_id' => 'nullable|integer',
        ]);

        $ticket = Ticket::find($id);

        if (! $ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        if ($ticket->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket is already closed',
            ], 400);
        }

        $agentId = $request->agent_id;

        if ($agentId) {
            $agent = TelegramUser::find($agentId);
            if (! $agent || ! $agent->isAgent()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid agent',
                ], 400);
            }
        }

        try {
            $this->ticketService->closeTicket($ticket, $agentId);

            // Send notification to client
            $bot = app(Nutgram::class);
            $templateService = app(TemplateService::class);

            $closeMessage = $templateService->getTemplate('messages.ticket_closed', $ticket->user->locale_id)
                ?? 'Your ticket has been closed. Thank you for contacting us!';

            $bot->sendMessage(
                text: $closeMessage,
                chat_id: $ticket->user->telegram_id,
                parse_mode: 'HTML'
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket closed successfully',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to close ticket', ['ticket_id' => $ticket->id, 'exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to close ticket',
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('from')) {
            $filters['from'] = $request->from;
        }
        if ($request->has('to')) {
            $filters['to'] = $request->to;
        }

        $statistics = $this->ticketService->getStatistics($filters);

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}
