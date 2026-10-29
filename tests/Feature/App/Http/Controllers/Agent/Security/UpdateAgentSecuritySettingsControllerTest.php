<?php

use Domain\Agent\Models\Agent;

/**
 * The Security page's rate-limit form saves the agent's and each visitor's
 * per-minute caps and the owner alert into the `security` settings group.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->owner = $this->agent->workspace->owner;
});

it('saves the agent and per-visitor rate limits and the owner alert', function (): void {
    $this->actingAs($this->owner)
        ->from(route('agents.security.rate-limit', $this->agent->external_id))
        ->post(route('agents.security.settings.update', $this->agent->external_id), [
            'chat_rate_limit_per_minute' => 50,
            'notify_owner_on_rate_limit' => true,
            'visitor_rate_limit_per_minute' => 5,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = $this->agent->fresh()->settings('security');

    expect($settings->get('chat_rate_limit_per_minute'))->toBe(50)
        ->and($settings->get('notify_owner_on_rate_limit'))->toBeTrue()
        ->and($settings->get('visitor_rate_limit_per_minute'))->toBe(5);
});
