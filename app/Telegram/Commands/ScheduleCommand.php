<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleOverride;
use App\Services\WorkScheduleService;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;

class ScheduleCommand extends BaseCommand
{
    protected string $command = 'schedule';

    protected ?string $description = 'Manage work schedule (admin only)';

    public function handle(Nutgram $bot): void
    {
        // Schedule management only works in private chat (text input requires MessageHandler)
        $serviceChatId = config('bot.service_chat.chat_id');
        if ($bot->chatId() == $serviceChatId) {
            $bot->sendMessage(
                text: '⚠️ Please use /schedule in private chat with the bot.',
                message_thread_id: $bot->message()?->message_thread_id
            );

            return;
        }

        $telegramId = $bot->userId();
        $user = TelegramUser::findOrCreateByTelegramId($telegramId, [
            'username' => $bot->user()->username,
            'first_name' => $bot->user()->first_name,
            'last_name' => $bot->user()->last_name,
        ]);

        // Admin check
        if (! $user->isAdmin()) {
            $bot->sendMessage(text: '⛔ Access denied. Admin only.');

            return;
        }

        $this->logCommand($bot, $user);

        self::showMainMenu($bot);
    }

    /**
     * Send or edit message with keyboard.
     * Edits existing message if $editMessageId provided, otherwise sends new one.
     */
    protected static function sendOrEdit(
        Nutgram $bot,
        string $text,
        InlineKeyboardMarkup $keyboard,
        ?int $editMessageId = null
    ): void {
        if ($editMessageId) {
            try {
                $bot->editMessageText(
                    text: $text,
                    chat_id: $bot->chatId(),
                    message_id: $editMessageId,
                    parse_mode: 'HTML',
                    reply_markup: $keyboard
                );

                return;
            } catch (\Throwable $e) {
                // Fall through to sendMessage if edit fails (e.g. message not modified)
            }
        }

        $bot->sendMessage(
            text: $text,
            parse_mode: 'HTML',
            reply_markup: $keyboard
        );
    }

    /**
     * Show schedule main menu
     */
    public static function showMainMenu(Nutgram $bot, ?int $editMessageId = null): void
    {
        $text = "📅 <b>Work Schedule Management</b>\n\n".
            'Choose an action:';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('📋 View current schedule', callback_data: 'schedule:view'))
            ->addRow(InlineKeyboardButton::make('✏️ Edit weekday', callback_data: 'schedule:edit_day'))
            ->addRow(InlineKeyboardButton::make('📌 Add date override', callback_data: 'schedule:override'))
            ->addRow(InlineKeyboardButton::make('🗑 Delete override', callback_data: 'schedule:delete_override'));

        self::sendOrEdit($bot, $text, $keyboard, $editMessageId);
    }

    /**
     * Show current schedule view
     */
    public static function showScheduleView(Nutgram $bot, ?int $editMessageId = null): void
    {
        $schedules = WorkSchedule::getAllOrdered();
        $overrides = WorkScheduleOverride::getUpcoming(5);
        $scheduleService = app(WorkScheduleService::class);

        $text = "📋 <b>Current Work Schedule</b>\n\n";

        if ($schedules->isEmpty()) {
            $text .= "<i>No schedule configured. All hours treated as working.</i>\n";
        } else {
            $text .= "<b>Weekly schedule:</b>\n";

            for ($day = 1; $day <= 7; $day++) {
                $schedule = $schedules->firstWhere('day_of_week', $day);
                $dayName = ucfirst(substr(WorkSchedule::DAY_NAMES[$day], 0, 3));

                if (! $schedule) {
                    $text .= "  {$dayName}: <i>not set</i>\n";
                } elseif (! $schedule->is_working_day) {
                    $text .= "  🔴 {$dayName}: Day off\n";
                } else {
                    $text .= "  🟢 {$dayName}: {$schedule->start_time} – {$schedule->end_time}\n";
                }
            }
        }

        if ($overrides->isNotEmpty()) {
            $text .= "\n<b>Upcoming overrides:</b>\n";

            foreach ($overrides as $override) {
                $dateStr = $override->date->format('d.m.Y');
                $desc = $override->description ? " ({$override->description})" : '';

                if (! $override->is_working_day) {
                    $text .= "  🔴 {$dateStr}: Day off{$desc}\n";
                } else {
                    $text .= "  🟡 {$dateStr}: {$override->start_time} – {$override->end_time}{$desc}\n";
                }
            }
        }

        $isWorking = $scheduleService->isWorkingHoursNow();
        $text .= "\n<b>Current status:</b> ".($isWorking ? '🟢 Working hours' : '🔴 Non-working hours');

        $nextWork = $scheduleService->formatNextWorkingTime();
        if (! $isWorking && $nextWork !== '—') {
            $text .= "\n<b>Next work time:</b> {$nextWork}";
        }

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(InlineKeyboardButton::make('⬅️ Back', callback_data: 'schedule:back'));

        self::sendOrEdit($bot, $text, $keyboard, $editMessageId);
    }

    /**
     * Show day selection for editing
     */
    public static function showDaySelection(Nutgram $bot, ?int $editMessageId = null): void
    {
        $text = '✏️ <b>Select day to edit:</b>';

        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $buttons = [];

        $row1 = [];
        for ($d = 1; $d <= 4; $d++) {
            $row1[] = ['text' => $dayNames[$d - 1], 'callback_data' => "schedule:day:{$d}"];
        }
        $buttons[] = $row1;

        $row2 = [];
        for ($d = 5; $d <= 7; $d++) {
            $row2[] = ['text' => $dayNames[$d - 1], 'callback_data' => "schedule:day:{$d}"];
        }
        $buttons[] = $row2;

        // Build keyboard: first 4 days row, last 3 days row, back button
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(...array_map(fn ($b) => InlineKeyboardButton::make($b['text'], callback_data: $b['callback_data']), $row1))
            ->addRow(...array_map(fn ($b) => InlineKeyboardButton::make($b['text'], callback_data: $b['callback_data']), $row2))
            ->addRow(InlineKeyboardButton::make('⬅️ Back', callback_data: 'schedule:back'));

        self::sendOrEdit($bot, $text, $keyboard, $editMessageId);
    }

    /**
     * Show day type selection (working/day off)
     */
    public static function showDayTypeSelection(Nutgram $bot, int $dayOfWeek, ?int $editMessageId = null): void
    {
        $dayName = ucfirst(WorkSchedule::DAY_NAMES[$dayOfWeek] ?? 'Unknown');
        $current = WorkSchedule::getForDay($dayOfWeek);

        $text = "📆 <b>{$dayName}</b>\n\n";

        if ($current) {
            if ($current->is_working_day) {
                $text .= "Current: 🟢 {$current->start_time} – {$current->end_time}\n\n";
            } else {
                $text .= "Current: 🔴 Day off\n\n";
            }
        } else {
            $text .= "Current: <i>not configured</i>\n\n";
        }

        $text .= 'Choose type:';

        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('🟢 Working day', callback_data: "schedule:day_type:{$dayOfWeek}:working"),
                InlineKeyboardButton::make('🔴 Day off', callback_data: "schedule:day_type:{$dayOfWeek}:dayoff")
            )
            ->addRow(InlineKeyboardButton::make('🗑 Remove', callback_data: "schedule:day_remove:{$dayOfWeek}"))
            ->addRow(InlineKeyboardButton::make('⬅️ Back', callback_data: 'schedule:edit_day'));

        self::sendOrEdit($bot, $text, $keyboard, $editMessageId);
    }

    /**
     * Show override list for deletion
     */
    public static function showOverrideList(Nutgram $bot, ?int $editMessageId = null): void
    {
        $overrides = WorkScheduleOverride::getUpcoming(20);

        if ($overrides->isEmpty()) {
            self::sendOrEdit(
                $bot,
                '📌 No upcoming overrides found.',
                InlineKeyboardMarkup::make()->addRow(InlineKeyboardButton::make('⬅️ Back', callback_data: 'schedule:back')),
                $editMessageId
            );

            return;
        }

        $text = "🗑 <b>Select override to delete:</b>\n";
        $buttons = [];

        foreach ($overrides as $override) {
            $dateStr = $override->date->format('d.m.Y');
            $desc = $override->description ? " ({$override->description})" : '';
            $status = $override->is_working_day ? '🟡' : '🔴';

            $buttons[] = [
                ['text' => "{$status} {$dateStr}{$desc}", 'callback_data' => "schedule:del_ovr:{$override->id}"],
            ];
        }

        $keyboard = InlineKeyboardMarkup::make();
        foreach ($overrides as $override) {
            $dateStr = $override->date->format('d.m.Y');
            $desc = $override->description ? " ({$override->description})" : '';
            $status = $override->is_working_day ? '🟡' : '🔴';
            $keyboard->addRow(
                InlineKeyboardButton::make("{$status} {$dateStr}{$desc}", callback_data: "schedule:del_ovr:{$override->id}")
            );
        }
        $keyboard->addRow(InlineKeyboardButton::make('⬅️ Back', callback_data: 'schedule:back'));

        self::sendOrEdit($bot, $text, $keyboard, $editMessageId);
    }
}
