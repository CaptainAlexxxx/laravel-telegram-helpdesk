<?php

namespace App\Console\Commands;

use App\Models\ConversationState;
use Illuminate\Console\Command;

class CleanExpiredConversationStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:clean-expired-states';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired conversation states';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = ConversationState::where('expires_at', '<', now())->delete();

        $this->info("Cleaned {$deleted} expired conversation states");

        return Command::SUCCESS;
    }
}
