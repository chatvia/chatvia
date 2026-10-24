<?php

use App\Identity\Workspace\Models\User;

/**
 * Opening an agent to the internet, or locking it again, takes an explicit
 * choice.
 */
it('locks a public agent again, and needs an explicit choice', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner, ['is_public' => true]);

    $this->actingAs($owner)
        ->patchJson(route('agents.security.update', $agent), [])
        ->assertJsonValidationErrors(['is_public']);
    expect($agent->fresh()->is_public)->toBeTrue();

    $this->actingAs($owner)
        ->patchJson(route('agents.security.update', $agent), ['is_public' => false])
        ->assertRedirect();
    expect($agent->fresh()->is_public)->toBeFalse();
});
