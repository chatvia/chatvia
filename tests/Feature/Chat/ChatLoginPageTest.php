<?php

use Domain\Agent\Models\Agent;
use Inertia\Testing\AssertableInertia;

/**
 * The guest login page of a protected agent is public, so it must only carry
 * what the login screen shows — never the Agent model (system prompt, owner ids).
 */
it('does not send the agent model or its system prompt to the guest login page', function (): void {
    $agent = Agent::factory()->create([
        'is_public' => false,
        'system_instructions' => 'SECRET SYSTEM PROMPT',
    ]);

    $response = $this->get("https://{$agent->sub_domain}.chatvia.test/chat/login");

    $response->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/LoginChatter')
            ->where('agentName', $agent->name)
            ->has('appearance')
            ->missing('agent')
        );

    expect($response->getContent())->not->toContain('SECRET SYSTEM PROMPT');
});
