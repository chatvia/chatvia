<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Revoking agents from a chatter through the public API: only the named
 * agents go, the chatter stays, and another workspace's agent is a field
 * error that leaves the grants as they were.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

it('revokes the named agents and keeps the rest', function (): void {
    $second = createAgent($this->owner);
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $chatter->agents()->attach($second->id, ['workspace_id' => $second->workspace_id]);

    $this->withToken($this->token)
        ->deleteJson("/api/v1/chatters/{$chatter->external_id}/agents", ['agent_ids' => [$this->agent->external_id]])
        ->assertNoContent();

    expect($chatter->agents()->pluck('agents.id')->all())->toEqual([$second->id]);
});

it('rejects a request without agent ids', function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();

    $this->withToken($this->token)
        ->deleteJson("/api/v1/chatters/{$chatter->external_id}/agents", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids']);
});

it("rejects another workspace's agent as a field error and keeps the grant", function (): void {
    $chatter = Chatter::factory()->grantedTo($this->agent)->create();
    $foreignAgent = createAgent(User::factory()->create());

    $this->withToken($this->token)
        ->deleteJson("/api/v1/chatters/{$chatter->external_id}/agents", ['agent_ids' => [$foreignAgent->external_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids.0']);

    expect($chatter->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
});
