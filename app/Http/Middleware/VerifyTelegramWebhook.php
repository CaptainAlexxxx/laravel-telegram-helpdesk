<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('nutgram.webhook_secret');

        if ($expected === '') {
            abort(500, 'TELEGRAM_WEBHOOK_SECRET is not configured');
        }

        $received = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! hash_equals($expected, $received)) {
            abort(403);
        }

        return $next($request);
    }
}
