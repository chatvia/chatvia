<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;

/**
 * Granting an existing chatter of the workspace access to a protected agent.
 * A public agent has no per-chatter access, so there is nothing to grant.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => false]);
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
});

it('grants an existing chatter of the workspace access to the agent', function (): void {
    $this->actingAs($this->owner)
        ->from(route('agents.chatters.index', $this->agent))
        ->post(route('agents.chatters.grant', [$this->agent, $this->chatter->external_id]))
        ->assertRedirect(route('agents.chatters.index', $this->agent));

    expect($this->chatter->fresh()->canAccessAgent($this->agent))->toBeTrue();
});

it('grants nothing on a public agent, which has no per-chatter access', function (): void {
    $this->agent->update(['is_public' => true]);

    $this->actingAs($this->owner)
        ->postJson(route('agents.chatters.grant', [$this->agent, $this->chatter->external_id]))
        ->assertUnprocessable();

    expect($this->agent->chatters()->whereKey($this->chatter->id)->exists())->toBeFalse();
});
