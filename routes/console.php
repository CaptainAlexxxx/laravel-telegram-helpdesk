<?php

use Illuminate\Support\Facades\Schedule;

// Clean expired conversation states every hour
Schedule::command('bot:clean-expired-states')->hourly();

// Clean old processed updates daily
Schedule::command('bot:clean-processed-updates')->daily();

// Send after-hours reminders when work hours start
Schedule::command('bot:send-after-hours-reminders')->everyMinute();
