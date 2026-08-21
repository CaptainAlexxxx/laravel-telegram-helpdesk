<?php

namespace App\Http\Controllers;

use App\Models\ProcessedUpdate;
use App\Telegram\RegisterHandlers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, Nutgram $bot)
    {
        $updateId = $request->input('update_id');

        if (! is_int($updateId)) {
            return response()->json(['ok' => false, 'error' => 'Invalid update'], 400);
        }

        // Telegram retries an update until it gets 2xx, so a duplicate is a success.
        if (! ProcessedUpdate::claim($updateId)) {
            return response()->json(['ok' => true, 'message' => 'Already processed']);
        }

        try {
            // Handlers are registered per request: each webhook call boots a fresh
            // Nutgram instance that resolves the incoming update inside run().
            RegisterHandlers::register($bot);
            $bot->run();
        } catch (\Throwable $e) {
            Log::error('Telegram webhook failed', [
                'update_id' => $updateId,
                'exception' => $e,
            ]);

            return response()->json(['ok' => false, 'error' => 'Internal error'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
