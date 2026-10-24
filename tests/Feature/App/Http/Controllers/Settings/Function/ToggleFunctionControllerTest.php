<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;

/**
 * Switching a webhook function off or on workspace-wide: a teammate with
 * account.functions switches the owner's.
 */
it('lets a teammate switch the workspace\'s function off', function (): void {
    $owner = User::factory()->create();
    $function = AgentFunction::factory()->forOwner($owner)->create(['is_enabled' => true]);
    $teammate = teammateWithPermissions($owner, ['account.functions']);

    $this->actingAs($teammate)
        ->post(route('integrations.functions.toggle', ['function' => $function->external_id]))
        ->assertRedirect();

    expect($function->fresh()->is_enabled)->toBeFalse();
});
