<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Telegram webhook URL for bot updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $token = config('nutgram.token');
        $webhookUrl = config('nutgram.webhook_url');
        $secret = config('nutgram.webhook_secret');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN not set in .env');

            return Command::FAILURE;
        }

        if (! $webhookUrl) {
            $this->error('TELEGRAM_WEBHOOK_URL not set in .env');
            $this->info('Set it like: TELEGRAM_WEBHOOK_URL=https://yourdomain.com/api/telegram/webhook');

            return Command::FAILURE;
        }

        if (! $secret) {
            $this->error('TELEGRAM_WEBHOOK_SECRET not set in .env');
            $this->info('Generate one with: php -r "echo bin2hex(random_bytes(32));"');

            return Command::FAILURE;
        }

        $this->info('Setting webhook...');
        $this->info("URL: {$webhookUrl}");

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query', 'edited_message'],
                'drop_pending_updates' => true,
                'secret_token' => $secret,
            ]);

            $result = $response->json();

            if ($result['ok'] ?? false) {
                $this->info('Webhook set successfully!');
                $this->info('Response: '.json_encode($result['result'] ?? true, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                // Get webhook info to verify
                $this->newLine();
                $this->info('Verifying webhook info...');
                $this->call('telegram:webhook-info');

                return Command::SUCCESS;
            } else {
                $this->error('Failed to set webhook');
                $this->error('Error: '.($result['description'] ?? 'Unknown error'));

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
