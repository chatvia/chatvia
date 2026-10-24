<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;

/**
 * Revoking a chatter's access to an agent from its Chatters page: the chatter
 * account and its other agents stay. A chatter never granted the agent is a
 * 404.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->agent = createAgent($this->owner, ['is_public' => false]);
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
});

it('revokes the agent from a chatter and keeps the chatter and its other agents', function (): void {
    $other = createAgent($this->owner, ['is_public' => false]);
    $this->chatter->agents()->attach([
        $this->agent->id => ['workspace_id' => $this->agent->workspace_id],
        $other->id => ['workspace_id' => $other->workspace_id],
    ]);

    $this->actingAs($this->owner)
        ->delete(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))
        ->assertRedirect();

    $chatter = $this->chatter->fresh();

    expect($chatter)->not->toBeNull()
        ->and($chatter->canAccessAgent($this->agent))->toBeFalse()
        ->and($chatter->canAccessAgent($other))->toBeTrue();
});

it('404s revoking a chatter the agent was never granted to', function (): void {
    $this->actingAs($this->owner)
        ->deleteJson(route('agents.chatters.destroy', [$this->agent, $this->chatter->external_id]))
        ->assertNotFound();
});
