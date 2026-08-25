<?php

namespace Tests\Unit;

use App\Models\WorkSchedule;
use App\Models\WorkScheduleOverride;
use App\Services\WorkScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Monday */
    private const MONDAY = '2026-06-15';

    private WorkScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bot.work_schedule.timezone' => 'UTC']);

        $this->service = app(WorkScheduleService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function seedWeek(string $start = '09:00', string $end = '18:00'): void
    {
        foreach (range(1, 7) as $day) {
            WorkSchedule::create([
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
                'is_working_day' => $day <= 5,
            ]);
        }
    }

    private function freezeAt(string $dateTime, string $timezone = 'UTC'): void
    {
        Carbon::setTestNow(Carbon::parse($dateTime, $timezone));
    }

    public function test_missing_schedule_does_not_block_the_bot(): void
    {
        $this->freezeAt(self::MONDAY.' 03:00');

        $this->assertTrue($this->service->isWorkingHoursNow());
    }

    public function test_time_inside_working_hours(): void
    {
        $this->seedWeek();
        $this->freezeAt(self::MONDAY.' 10:00');

        $this->assertTrue($this->service->isWorkingHoursNow());
    }

    public function test_boundaries_are_start_inclusive_and_end_exclusive(): void
    {
        $this->seedWeek();

        $this->freezeAt(self::MONDAY.' 09:00');
        $this->assertTrue($this->service->isWorkingHoursNow());

        $this->freezeAt(self::MONDAY.' 18:00');
        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_time_outside_working_hours(): void
    {
        $this->seedWeek();
        $this->freezeAt(self::MONDAY.' 20:00');

        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_non_working_weekday(): void
    {
        $this->seedWeek();
        $this->freezeAt('2026-06-20 12:00'); // Saturday

        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_override_wins_over_weekly_schedule(): void
    {
        $this->seedWeek();

        WorkScheduleOverride::create([
            'date' => self::MONDAY,
            'is_working_day' => false,
            'description' => 'Public holiday',
        ]);

        $this->freezeAt(self::MONDAY.' 10:00');

        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_override_can_open_a_non_working_day(): void
    {
        $this->seedWeek();

        WorkScheduleOverride::create([
            'date' => '2026-06-20', // Saturday
            'is_working_day' => true,
            'start_time' => '10:00',
            'end_time' => '14:00',
        ]);

        $this->freezeAt('2026-06-20 11:00');
        $this->assertTrue($this->service->isWorkingHoursNow());

        $this->freezeAt('2026-06-20 15:00');
        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_schedule_is_evaluated_in_the_configured_timezone(): void
    {
        $this->seedWeek();
        config(['bot.work_schedule.timezone' => 'Europe/Kyiv']);

        // 06:30 UTC is 09:30 in Kyiv — outside the window by UTC, inside by Kyiv.
        $this->freezeAt(self::MONDAY.' 06:30');
        $this->assertTrue($this->service->isWorkingHoursNow());

        // 16:00 UTC is 19:00 in Kyiv — the mirror case.
        $this->freezeAt(self::MONDAY.' 16:00');
        $this->assertFalse($this->service->isWorkingHoursNow());
    }

    public function test_next_working_time_rolls_over_to_the_following_day(): void
    {
        $this->seedWeek();
        $this->freezeAt(self::MONDAY.' 20:00');

        $next = $this->service->getNextWorkingTime();

        $this->assertNotNull($next);
        $this->assertSame('2026-06-16 09:00', $next->format('Y-m-d H:i'));
    }

    public function test_next_working_time_skips_the_weekend(): void
    {
        $this->seedWeek();
        $this->freezeAt('2026-06-19 20:00'); // Friday evening

        $next = $this->service->getNextWorkingTime();

        $this->assertNotNull($next);
        $this->assertSame('2026-06-22 09:00', $next->format('Y-m-d H:i'));
    }
}
