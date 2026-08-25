<?php

use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('tickets')->group(function () {
        Route::get('/', [TicketController::class, 'index']);
        Route::get('/{id}', [TicketController::class, 'show']);
        Route::post('/{id}/messages', [TicketController::class, 'sendMessage']);
        Route::put('/{id}/close', [TicketController::class, 'close']);
    });

    Route::get('/statistics', [TicketController::class, 'statistics']);
});
