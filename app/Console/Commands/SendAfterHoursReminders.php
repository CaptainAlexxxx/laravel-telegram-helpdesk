<?php

namespace App\Console\Commands;

use App\Jobs\SendAfterHoursRemindersJob;
use Illuminate\Console\Command;

class SendAfterHoursReminders extends Command
{
    protected $signature = 'bot:send-after-hours-reminders';

    protected $description = 'Send reminders about clients who wrote during non-working hours';

    public function handle(): int
    {
        SendAfterHoursRemindersJob::dispatchSync();

        return Command::SUCCESS;
    }
}
