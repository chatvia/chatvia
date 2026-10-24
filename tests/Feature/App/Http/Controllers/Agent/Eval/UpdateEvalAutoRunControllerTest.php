<?php

use App\Identity\Workspace\Models\User;

/**
 * "Run evals after changes" is saved on the agent by someone who manages its
 * evals, and refused for someone who may only view them.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner);
});

it('turns automatic eval runs on and off', function (): void {
    $this->actingAs($this->owner)->put(route('agents.evals.auto-run', $this->agent), ['auto_run' => true])->assertRedirect();
    expect($this->agent->fresh()->settings('evals')->get('auto_run'))->toBeTrue();

    $this->actingAs($this->owner)->put(route('agents.evals.auto-run', $this->agent), ['auto_run' => false])->assertRedirect();
    expect($this->agent->fresh()->settings('evals')->get('auto_run'))->toBeFalse();
});

it('refuses a teammate who may only view the evals', function (): void {
    $viewer = teammateWithPermissions($this->owner, ['agent.evals.view']);

    $this->actingAs($viewer)->put(route('agents.evals.auto-run', $this->agent), ['auto_run' => true])->assertForbidden();
});
