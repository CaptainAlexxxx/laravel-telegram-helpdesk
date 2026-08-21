<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GetTelegramWebhookInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get current Telegram webhook information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('nutgram.token');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN not set in .env');

            return Command::FAILURE;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/getWebhookInfo");
            $result = $response->json();

            if ($result['ok'] ?? false) {
                $info = $result['result'];

                $this->info('Webhook Information:');
                $this->table(
                    ['Parameter', 'Value'],
                    [
                        ['URL', $info['url'] ?? 'Not set'],
                        ['Has Custom Certificate', $info['has_custom_certificate'] ?? false ? 'Yes' : 'No'],
                        ['Pending Update Count', $info['pending_update_count'] ?? 0],
                        ['Last Error Date', isset($info['last_error_date']) ? date('Y-m-d H:i:s', $info['last_error_date']) : 'None'],
                        ['Last Error Message', $info['last_error_message'] ?? 'None'],
                        ['Max Connections', $info['max_connections'] ?? 40],
                        ['Allowed Updates', implode(', ', $info['allowed_updates'] ?? [])],
                    ]
                );

                if (isset($info['last_error_message'])) {
                    $this->warn('Last error: '.$info['last_error_message']);
                }

                return Command::SUCCESS;
            } else {
                $this->error('Failed to get webhook info');

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
