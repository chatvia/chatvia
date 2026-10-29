<?php

use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Models\AgentChatterInvitation;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Socialite\Facades\Socialite;

/**
 * A failed provider sign-in on the agent-invitation social flow sends the user
 * back to login, and the failure is reported instead of swallowed.
 */
it('sends a failed provider sign-in back to login and reports the failure', function (): void {
    Exceptions::fake();
    $invitation = AgentChatterInvitation::factory()->forAgent(Agent::factory()->create())->create();
    Socialite::shouldReceive('driver')->andThrow(new RuntimeException('provider unreachable'));

    $this->withSession(['pending_agent_invitation' => ['invitation_id' => $invitation->id, 'provider' => 'google']])
        ->get(route('agents.invitation.social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    Exceptions::assertReported(RuntimeException::class);
});
