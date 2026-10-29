<?php

use App\Identity\Workspace\Models\User;

/**
 * "Run evals after changes" is saved on the agent by someone who manages its
 * evals, and refused for someone who may only view them.
 */
it('saves automatic eval runs for an eval manager and refuses a view-only teammate', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $viewer = teammateWithPermissions($owner, ['agent.evals.view']);

    $this->actingAs($viewer)->put(route('agents.evals.auto-run', $agent), ['auto_run' => true])->assertForbidden();

    expect($agent->fresh()->settings('evals')->get('auto_run'))->not->toBeTrue();

    $this->actingAs($owner)->put(route('agents.evals.auto-run', $agent), ['auto_run' => true])->assertRedirect();

    expect($agent->fresh()->settings('evals')->get('auto_run'))->toBeTrue();
});
