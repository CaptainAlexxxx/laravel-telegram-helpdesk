<?php

namespace App\Providers;

use App\Services\AgentGuard;
use App\Services\ConversationService;
use App\Services\MessageSplitter;
use App\Services\NotificationService;
use App\Services\TelegramService;
use App\Services\TemplateService;
use App\Services\TicketService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register services as singletons
        $this->app->singleton(TemplateService::class);
        $this->app->singleton(TicketService::class);
        $this->app->singleton(TelegramService::class);
        $this->app->singleton(ConversationService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(MessageSplitter::class);
        $this->app->singleton(AgentGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
