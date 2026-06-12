<?php

/**
 * The Security page's rate-limit form persists into the `security` model-settings
 * group and validates the per-minute cap.
 */
beforeEach(function (): void {
    // proPlanAgent() gives the owner an active subscription so the agent route
    // group's subscription gate doesn't redirect before the controller runs.
    $this->agent = proPlanAgent();
    $this->owner = $this->agent->user;
});

it('saves the agent chat rate-limit settings', function (): void {
    $this->actingAs($this->owner)
        ->from(route('agents.security.rate-limit', $this->agent->external_id))
        ->post(route('agents.security.settings.update', $this->agent->external_id), [
            'chat_rate_limit_per_minute' => 50,
            'notify_owner_on_rate_limit' => true,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = $this->agent->fresh()->settings('security');

    expect($settings->get('chat_rate_limit_per_minute'))->toBe(50)
        ->and($settings->get('notify_owner_on_rate_limit'))->toBeTrue();
});

it('rejects a rate limit outside the allowed range', function (): void {
    $this->actingAs($this->owner)
        ->from(route('agents.security.rate-limit', $this->agent->external_id))
        ->post(route('agents.security.settings.update', $this->agent->external_id), [
            'chat_rate_limit_per_minute' => 0,
            'notify_owner_on_rate_limit' => false,
        ])
        ->assertSessionHasErrors('chat_rate_limit_per_minute');
});
