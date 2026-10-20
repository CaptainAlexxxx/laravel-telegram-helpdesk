<?php

namespace Tests\Feature;

use App\Telegram\Handlers\ClientMediaGroupHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;
use Tests\TestCase;

class MediaGroupDebounceTest extends TestCase
{
    use RefreshDatabase;

    private const CACHE_KEY = 'media_group:555:album-1';

    private const LOCK_KEY = 'media_group_lock:555:album-1';

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Carbon::setTestNow('2026-01-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function receiveItem(int $messageId): void
    {
        $bot = Nutgram::fake();
        $bot->onMessage(ClientMediaGroupHandler::class);

        $bot->hearMessage([
            'message_id' => $messageId,
            'media_group_id' => 'album-1',
            'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Client'],
            'chat' => ['id' => 555, 'type' => 'private'],
            'photo' => [['file_id' => "f{$messageId}", 'file_unique_id' => "u{$messageId}", 'width' => 1, 'height' => 1]],
        ])->reply();
    }

    /**
     * @return array<int, CallQueuedClosure>
     */
    private function dispatchedChecks(): array
    {
        return Bus::dispatched(CallQueuedClosure::class)->values()->all();
    }

    public function test_processing_is_deferred_until_album_goes_quiet(): void
    {
        $this->receiveItem(1);

        $jobs = $this->dispatchedChecks();
        $this->assertCount(1, $jobs);
        $this->assertSame(ClientMediaGroupHandler::ALBUM_TIMEOUT, $jobs[0]->delay);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->receiveItem(2);

        $this->assertCount(1, $this->dispatchedChecks());
        $this->assertSame([1, 2], array_column(Cache::get(self::CACHE_KEY)['messages'], 'message_id'));

        Carbon::setTestNow(now()->addSecond());
        $jobs[0]->handle(app());

        $jobs = $this->dispatchedChecks();
        $this->assertCount(2, $jobs);
        $this->assertSame(2, $jobs[1]->delay);
        $this->assertTrue(Cache::has(self::CACHE_KEY));

        Carbon::setTestNow(now()->addSeconds(2));
        $jobs[1]->handle(app());

        $this->assertCount(2, $this->dispatchedChecks());
        $this->assertFalse(Cache::has(self::CACHE_KEY));
        $this->assertFalse(Cache::has(self::LOCK_KEY));
    }

    public function test_processing_is_forced_after_max_wait(): void
    {
        $this->receiveItem(1);
        $job = $this->dispatchedChecks()[0];

        Carbon::setTestNow(now()->addSeconds(ClientMediaGroupHandler::MAX_WAIT));
        $this->receiveItem(2);

        $job->handle(app());

        $this->assertCount(1, $this->dispatchedChecks());
        $this->assertFalse(Cache::has(self::CACHE_KEY));
        $this->assertFalse(Cache::has(self::LOCK_KEY));
    }

    public function test_item_arriving_after_processing_starts_a_new_album(): void
    {
        $this->receiveItem(1);
        $job = $this->dispatchedChecks()[0];

        Carbon::setTestNow(now()->addSeconds(ClientMediaGroupHandler::ALBUM_TIMEOUT));
        $job->handle(app());

        $this->assertFalse(Cache::has(self::LOCK_KEY));

        $this->receiveItem(2);

        $jobs = $this->dispatchedChecks();
        $this->assertCount(2, $jobs);
        $this->assertSame(ClientMediaGroupHandler::ALBUM_TIMEOUT, $jobs[1]->delay);
        $this->assertSame([2], array_column(Cache::get(self::CACHE_KEY)['messages'], 'message_id'));
        $this->assertTrue(Cache::has(self::LOCK_KEY));
    }
}
