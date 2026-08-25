<?php

namespace Tests\Feature;

use App\Models\ProcessedUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private function sendUpdate(array $payload, ?string $secret = self::SECRET)
    {
        $headers = $secret === null ? [] : ['X-Telegram-Bot-Api-Secret-Token' => $secret];

        return $this->postJson('/api/telegram/webhook', $payload, $headers);
    }

    public function test_request_without_secret_header_is_rejected(): void
    {
        $this->sendUpdate(['update_id' => 1], null)->assertForbidden();
    }

    public function test_request_with_wrong_secret_is_rejected(): void
    {
        $this->sendUpdate(['update_id' => 1], 'nope')->assertForbidden();
    }

    public function test_payload_without_numeric_update_id_is_rejected(): void
    {
        $this->sendUpdate(['update_id' => 'abc'])
            ->assertStatus(400)
            ->assertJsonPath('ok', false);

        $this->assertDatabaseCount('processed_updates', 0);
    }

    public function test_already_processed_update_is_acknowledged_without_reprocessing(): void
    {
        ProcessedUpdate::claim(42);

        $this->sendUpdate(['update_id' => 42])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('message', 'Already processed');

        $this->assertDatabaseCount('processed_updates', 1);
    }
}
