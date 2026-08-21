<?php

namespace App\Console\Commands;

use App\Models\ProcessedUpdate;
use Illuminate\Console\Command;

class CleanProcessedUpdates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:clean-processed-updates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old processed updates (older than 7 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deleted = ProcessedUpdate::cleanOld();

        $this->info("Cleaned {$deleted} old processed updates");

        return Command::SUCCESS;
    }
}
