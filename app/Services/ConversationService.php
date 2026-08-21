<?php

namespace App\Services;

use App\Models\ConversationState;
use App\Models\TelegramUser;

class ConversationService
{
    /**
     * Get current state for user
     */
    public function getState(TelegramUser $user): ?string
    {
        $state = ConversationState::getForUser($user->id);

        return $state?->state;
    }

    /**
     * Get state data for user
     */
    public function getStateData(TelegramUser $user): ?array
    {
        $state = ConversationState::getForUser($user->id);

        return $state?->data;
    }

    /**
     * Set state for user
     */
    public function setState(TelegramUser $user, string $state, ?array $data = null, ?int $expiresInMinutes = 30): void
    {
        ConversationState::setForUser($user->id, $state, $data, $expiresInMinutes);
    }

    /**
     * Clear state for user
     */
    public function clearState(TelegramUser $user): void
    {
        ConversationState::clearForUser($user->id);
    }

    /**
     * Check if user is in specific state
     */
    public function isInState(TelegramUser $user, string $state): bool
    {
        return $this->getState($user) === $state;
    }

    /**
     * Check if user has any active state
     */
    public function hasActiveState(TelegramUser $user): bool
    {
        return $this->getState($user) !== null;
    }
}
