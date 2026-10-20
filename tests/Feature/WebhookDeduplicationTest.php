<?php

namespace Tests\Feature;

use App\Models\ProcessedUpdate;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\Bus;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class WebhookDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_update_posted_twice_is_processed_once(): void
    {
        Bus::fake();

        app(Nutgram::class)->hearMessage([
            'message_id' => 10,
            'media_group_id' => 'album-1',
            'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Client'],
            'chat' => ['id' => 555, 'type' => 'private'],
            'photo' => [['file_id' => 'f1', 'file_unique_id' => 'u1', 'width' => 1, 'height' => 1]],
        ]);

        $headers = ['X-Telegram-Bot-Api-Secret-Token' => 'test-webhook-secret'];

        $this->postJson('/api/telegram/webhook', ['update_id' => 777], $headers)
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->postJson('/api/telegram/webhook', ['update_id' => 777], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Already processed');

        $this->assertDatabaseCount('processed_updates', 1);
        Bus::assertDispatchedTimes(CallQueuedClosure::class, 1);
    }

    public function test_duplicate_update_id_violates_unique_constraint(): void
    {
        ProcessedUpdate::create(['update_id' => 900, 'processed_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);

        ProcessedUpdate::create(['update_id' => 900, 'processed_at' => now()]);
    }
}
