<?php

namespace App\Services;

use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;

class AgentGuard
{
    /**
     * Check if user is allowed to be an agent
     */
    public function isAllowedAgent(int $telegramId): bool
    {
        // If whitelist is disabled, allow all
        if (! config('bot.agents.whitelist_enabled', false)) {
            return true;
        }

        $whitelist = config('bot.agents.whitelist', []);

        // Check if user is in whitelist
        return in_array($telegramId, $whitelist);
    }

    /**
     * Check if user can perform agent actions
     */
    public function canPerformAgentAction(TelegramUser $user): bool
    {
        // Check if user has agent role
        if (! $user->isAgent()) {
            Log::warning('Non-agent user attempted agent action', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'role' => $user->role,
            ]);

            return false;
        }

        // If whitelist is enabled, check whitelist
        if (config('bot.agents.whitelist_enabled', false)) {
            if (! $this->isAllowedAgent($user->telegram_id)) {
                Log::warning('User not in agent whitelist attempted action', [
                    'user_id' => $user->id,
                    'telegram_id' => $user->telegram_id,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Promote user to agent if allowed
     */
    public function promoteToAgentIfAllowed(TelegramUser $user): bool
    {
        // Check if auto-promote is enabled
        if (! config('bot.agents.auto_promote_in_service_chat', true)) {
            return false;
        }

        // Check whitelist
        if (! $this->isAllowedAgent($user->telegram_id)) {
            Log::warning('Cannot promote user to agent: not in whitelist', [
                'telegram_id' => $user->telegram_id,
            ]);

            return false;
        }

        // Promote user
        if ($user->role === 'client') {
            $user->update(['role' => 'agent']);

            Log::info('User promoted to agent', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
            ]);

            return true;
        }

        return false;
    }
}
