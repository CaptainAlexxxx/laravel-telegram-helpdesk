<?php

namespace Tests\Feature;

use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    private function makeClient(int $telegramId = 111): TelegramUser
    {
        return TelegramUser::create([
            'telegram_id' => $telegramId,
            'first_name' => 'Client',
            'role' => 'client',
        ]);
    }

    private function makeTicket(string $status = 'open', ?TelegramUser $client = null): Ticket
    {
        return Ticket::create([
            'user_id' => ($client ?? $this->makeClient())->id,
            'subject' => 'Payment failed',
            'status' => $status,
        ]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/tickets')->assertUnauthorized();
        $this->getJson('/api/statistics')->assertUnauthorized();
    }

    public function test_index_returns_tickets_with_pagination_meta(): void
    {
        $this->authenticate();
        $this->makeTicket();

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.subject', 'Payment failed');
    }

    public function test_index_filters_by_status(): void
    {
        $this->authenticate();

        $client = $this->makeClient();
        $this->makeTicket('open', $client);
        $this->makeTicket('closed', $client);

        $this->getJson('/api/tickets?status=closed')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'closed');
    }

    public function test_page_size_is_clamped(): void
    {
        $this->authenticate();

        $this->getJson('/api/tickets?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);

        $this->getJson('/api/tickets?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_show_returns_404_for_unknown_ticket(): void
    {
        $this->authenticate();

        $this->getJson('/api/tickets/999')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_ticket_with_history(): void
    {
        $this->authenticate();
        $ticket = $this->makeTicket();

        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.ticket.id', $ticket->id)
            ->assertJsonPath('data.history', []);
    }

    public function test_send_message_validates_payload(): void
    {
        $this->authenticate();
        $ticket = $this->makeTicket();

        $this->postJson("/api/tickets/{$ticket->id}/messages", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agent_id', 'message']);
    }

    public function test_send_message_is_rejected_on_closed_ticket(): void
    {
        $this->authenticate();
        $ticket = $this->makeTicket('closed');

        $this->postJson("/api/tickets/{$ticket->id}/messages", [
            'agent_id' => 1,
            'message' => 'Hello',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Ticket is closed');
    }

    public function test_send_message_is_rejected_for_non_agent(): void
    {
        $this->authenticate();

        $client = $this->makeClient();
        $ticket = $this->makeTicket('open', $client);

        $this->postJson("/api/tickets/{$ticket->id}/messages", [
            'agent_id' => $client->id,
            'message' => 'Hello',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid agent');
    }

    public function test_closing_an_already_closed_ticket_is_rejected(): void
    {
        $this->authenticate();
        $ticket = $this->makeTicket('closed');

        $this->putJson("/api/tickets/{$ticket->id}/close")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Ticket is already closed');
    }
}
