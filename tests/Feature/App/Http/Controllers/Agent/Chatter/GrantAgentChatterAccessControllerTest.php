<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;

/**
 * Granting an existing chatter of the workspace access to a protected agent.
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
