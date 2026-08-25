<?php

namespace Tests\Unit;

use App\Models\TelegramUser;
use App\Services\AgentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentGuardTest extends TestCase
{
    use RefreshDatabase;

    private AgentGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new AgentGuard;
    }

    private function makeUser(string $role = 'client', int $telegramId = 111): TelegramUser
    {
        return TelegramUser::create([
            'telegram_id' => $telegramId,
            'first_name' => 'Test',
            'role' => $role,
        ]);
    }

    public function test_everyone_is_allowed_when_whitelist_is_disabled(): void
    {
        config(['bot.agents.whitelist_enabled' => false]);

        $this->assertTrue($this->guard->isAllowedAgent(999));
    }

    public function test_whitelist_rejects_unknown_telegram_id(): void
    {
        config([
            'bot.agents.whitelist_enabled' => true,
            'bot.agents.whitelist' => ['111'],
        ]);

        $this->assertTrue($this->guard->isAllowedAgent(111));
        $this->assertFalse($this->guard->isAllowedAgent(999));
    }

    public function test_client_cannot_perform_agent_action(): void
    {
        config(['bot.agents.whitelist_enabled' => false]);

        $this->assertFalse($this->guard->canPerformAgentAction($this->makeUser('client')));
    }

    public function test_agent_can_perform_agent_action(): void
    {
        config(['bot.agents.whitelist_enabled' => false]);

        $this->assertTrue($this->guard->canPerformAgentAction($this->makeUser('agent')));
    }

    public function test_agent_outside_whitelist_cannot_perform_agent_action(): void
    {
        config([
            'bot.agents.whitelist_enabled' => true,
            'bot.agents.whitelist' => ['222'],
        ]);

        $this->assertFalse($this->guard->canPerformAgentAction($this->makeUser('agent', 111)));
    }

    public function test_whitelisted_client_is_promoted_to_agent(): void
    {
        config([
            'bot.agents.auto_promote_in_service_chat' => true,
            'bot.agents.whitelist_enabled' => true,
            'bot.agents.whitelist' => ['111'],
        ]);

        $user = $this->makeUser('client', 111);

        $this->assertTrue($this->guard->promoteToAgentIfAllowed($user));
        $this->assertSame('agent', $user->fresh()->role);
    }

    public function test_promotion_is_skipped_when_auto_promote_is_disabled(): void
    {
        config([
            'bot.agents.auto_promote_in_service_chat' => false,
            'bot.agents.whitelist_enabled' => false,
        ]);

        $user = $this->makeUser('client');

        $this->assertFalse($this->guard->promoteToAgentIfAllowed($user));
        $this->assertSame('client', $user->fresh()->role);
    }

    public function test_existing_agent_is_not_promoted_again(): void
    {
        config([
            'bot.agents.auto_promote_in_service_chat' => true,
            'bot.agents.whitelist_enabled' => false,
        ]);

        $this->assertFalse($this->guard->promoteToAgentIfAllowed($this->makeUser('agent')));
    }
}
