<?php

namespace App\Services;

use App\Models\AfterHoursMessage;
use App\Models\TelegramUser;
use App\Models\Ticket;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class WorkScheduleService
{
    protected TemplateService $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Get timezone for schedule calculations
     */
    protected function getTimezone(): string
    {
        return config('bot.work_schedule.timezone', config('app.timezone', 'UTC'));
    }

    /**
     * Get current time in configured timezone
     */
    protected function now(): Carbon
    {
        return now()->timezone($this->getTimezone());
    }

    /**
     * Check if current time is within working hours
     */
    public function isWorkingHoursNow(): bool
    {
        $now = $this->now();
        $schedule = $this->getScheduleForDate($now);

        if (! $schedule) {
            // No schedule configured — assume working hours (don't block)
            return true;
        }

        if (! $schedule['is_working']) {
            return false;
        }

        $currentTime = $now->format('H:i');

        return $currentTime >= $schedule['start_time'] && $currentTime < $schedule['end_time'];
    }

    /**
     * Get effective schedule for a specific date
     * Returns: ['is_working' => bool, 'start_time' => 'HH:mm', 'end_time' => 'HH:mm'] or null
     */
    public function getScheduleForDate(Carbon $date): ?array
    {
        // Check override first (priority)
        $override = WorkScheduleOverride::getForDate($date);

        if ($override) {
            return [
                'is_working' => $override->is_working_day,
                'start_time' => $override->start_time,
                'end_time' => $override->end_time,
            ];
        }

        // Fallback to weekly schedule
        $dayOfWeek = (int) $date->format('N'); // ISO-8601: 1=Monday, 7=Sunday
        $weeklySchedule = WorkSchedule::getForDay($dayOfWeek);

        if (! $weeklySchedule) {
            return null; // No schedule configured for this day
        }

        return [
            'is_working' => $weeklySchedule->is_working_day,
            'start_time' => $weeklySchedule->start_time,
            'end_time' => $weeklySchedule->end_time,
        ];
    }

    /**
     * Get the next working time from now
     */
    public function getNextWorkingTime(): ?Carbon
    {
        $now = $this->now();

        // Check today first — maybe work hours haven't started yet
        $todaySchedule = $this->getScheduleForDate($now);

        if ($todaySchedule && $todaySchedule['is_working']) {
            $startToday = $now->copy()->setTimeFromTimeString($todaySchedule['start_time']);

            if ($now->lt($startToday)) {
                return $startToday;
            }
        }

        // Check next 14 days
        for ($i = 1; $i <= 14; $i++) {
            $checkDate = $now->copy()->addDays($i)->startOfDay();
            $schedule = $this->getScheduleForDate($checkDate);

            if ($schedule && $schedule['is_working']) {
                return $checkDate->setTimeFromTimeString($schedule['start_time']);
            }
        }

        return null;
    }

    /**
     * Format next working time for the {workTime} template variable, e.g. "Mon 09:00".
     */
    public function formatNextWorkingTime(?int $localeId = null): string
    {
        $nextTime = $this->getNextWorkingTime();

        if (! $nextTime) {
            return '—';
        }

        $dayOfWeek = (int) $nextTime->format('N');
        $dayName = WorkSchedule::getDayName($dayOfWeek);

        // Get localized day name from templates
        $localizedDay = $this->templateService->getTemplate("day.{$dayName}", $localeId);

        if (! $localizedDay) {
            $fallbackDays = [
                1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
                5 => 'Fri', 6 => 'Sat', 7 => 'Sun',
            ];
            $localizedDay = $fallbackDays[$dayOfWeek] ?? '?';
        }

        return $localizedDay.' '.$nextTime->format('H:i');
    }

    /**
     * Calculate start of the current non-working period
     * Used for deduplication of auto-replies
     */
    public function getCurrentNonWorkingPeriodStart(): Carbon
    {
        $now = $this->now();

        // Check today's schedule
        $todaySchedule = $this->getScheduleForDate($now);

        if ($todaySchedule && $todaySchedule['is_working']) {
            $endTime = $now->copy()->setTimeFromTimeString($todaySchedule['end_time']);

            // After end_time today — period started at end_time
            if ($now->gte($endTime)) {
                return $endTime;
            }

            // Before start_time today — look backwards for previous day's end
        }

        // Walk backwards to find the last working day's end_time
        for ($i = 1; $i <= 14; $i++) {
            $prevDate = $now->copy()->subDays($i)->startOfDay();
            $prevSchedule = $this->getScheduleForDate($prevDate);

            if ($prevSchedule && $prevSchedule['is_working']) {
                return $prevDate->copy()->setTimeFromTimeString($prevSchedule['end_time']);
            }
        }

        // Fallback — use start of today
        return $now->copy()->startOfDay();
    }

    /**
     * Check if auto-reply should be sent to this user in current non-working period
     */
    public function shouldSendAutoReply(TelegramUser $user): bool
    {
        $periodStart = $this->getCurrentNonWorkingPeriodStart();

        $existing = AfterHoursMessage::getForUserInPeriod($user->id, $periodStart);

        return ! $existing || ! $existing->auto_reply_sent;
    }

    /**
     * Create or update after-hours record and optionally mark auto-reply as sent
     */
    public function recordAfterHoursMessage(TelegramUser $user, ?Ticket $ticket, bool $markAutoReplySent = true): AfterHoursMessage
    {
        $periodStart = $this->getCurrentNonWorkingPeriodStart();

        $record = AfterHoursMessage::getForUserInPeriod($user->id, $periodStart);

        if ($record) {
            $updateData = [];

            if ($ticket && ! $record->ticket_id) {
                $updateData['ticket_id'] = $ticket->id;
            }

            if ($markAutoReplySent && ! $record->auto_reply_sent) {
                $updateData['auto_reply_sent'] = true;
            }

            if (! empty($updateData)) {
                $record->update($updateData);
            }

            return $record;
        }

        // Create new record
        return AfterHoursMessage::create([
            'telegram_user_id' => $user->id,
            'ticket_id' => $ticket?->id,
            'period_start' => $periodStart,
            'auto_reply_sent' => $markAutoReplySent,
            'reminder_needed' => true,
            'reminder_sent' => false,
        ]);
    }

    /**
     * Get the after-hours auto-reply text for client
     * Tries day-specific template first, then fallback
     */
    public function getAutoReplyText(TelegramUser $user): ?string
    {
        $now = $this->now();
        $dayOfWeek = (int) $now->format('N');
        $dayName = WorkSchedule::getDayName($dayOfWeek);

        $vars = [
            'currentTime' => $now->format('H:i'),
            'workTime' => $this->formatNextWorkingTime($user->locale_id),
        ];

        // Try day-specific template first
        $text = $this->templateService->getTemplateWithVars(
            "messages.after_hours_reply.{$dayName}",
            $vars,
            $user->locale_id
        );

        if ($text) {
            return $text;
        }

        // Fallback to generic template
        return $this->templateService->getTemplateWithVars(
            'messages.after_hours_reply',
            $vars,
            $user->locale_id
        );
    }

    /**
     * Handle agent reply during non-working hours
     * Sets reminder_needed to false by default (cancel reminder)
     */
    public function handleAgentReplyAfterHours(TelegramUser $clientUser): ?AfterHoursMessage
    {
        if ($this->isWorkingHoursNow()) {
            return null;
        }

        $periodStart = $this->getCurrentNonWorkingPeriodStart();
        $record = AfterHoursMessage::getForUserInPeriod($clientUser->id, $periodStart);

        if ($record && ! $record->reminder_sent && $record->reminder_needed) {
            // First agent reply: cancel reminder by default and show prompt
            $record->update(['reminder_needed' => false]);

            return $record;
        }

        return null;
    }

    /**
     * Confirm reminder (agent chose "Yes")
     */
    public function confirmReminder(int $afterHoursMessageId): void
    {
        $record = AfterHoursMessage::find($afterHoursMessageId);

        if ($record) {
            $record->update(['reminder_needed' => true]);

            Log::info('After-hours reminder confirmed', [
                'after_hours_message_id' => $afterHoursMessageId,
                'user_id' => $record->telegram_user_id,
            ]);
        }
    }

    /**
     * Cancel reminder (agent chose "No")
     */
    public function cancelReminder(int $afterHoursMessageId): void
    {
        $record = AfterHoursMessage::find($afterHoursMessageId);

        if ($record) {
            $record->update(['reminder_needed' => false]);

            Log::info('After-hours reminder cancelled', [
                'after_hours_message_id' => $afterHoursMessageId,
                'user_id' => $record->telegram_user_id,
            ]);
        }
    }

    /**
     * Get all pending reminders that should be sent now
     */
    public function getPendingReminders(): Collection
    {
        if (! $this->isWorkingHoursNow()) {
            return collect();
        }

        return AfterHoursMessage::getPendingReminders();
    }

    /**
     * Mark reminder as sent
     */
    public function markReminderSent(AfterHoursMessage $record): void
    {
        $record->update(['reminder_sent' => true]);
    }
}
