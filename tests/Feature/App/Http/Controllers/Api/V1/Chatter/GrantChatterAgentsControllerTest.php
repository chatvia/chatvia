<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Granting a chatter more agents through the public API: a repeat grant
 * changes nothing, and another workspace's agent is a field error.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->chatter = Chatter::factory()->ofWorkspace($this->owner)->create();
    $this->uri = "/api/v1/chatters/{$this->chatter->external_id}/agents";
});

it('grants an agent once, however often it is asked', function (): void {
    $this->withToken($this->token)->postJson($this->uri, ['agent_ids' => [$this->agent->external_id]])->assertNoContent();
    $this->withToken($this->token)->postJson($this->uri, ['agent_ids' => [$this->agent->external_id]])->assertNoContent();

    expect($this->chatter->agents()->whereKey($this->agent->id)->count())->toBe(1);
});

it("rejects another workspace's agent as a field error", function (): void {
    $foreignAgent = createAgent(User::factory()->create());

    $this->withToken($this->token)
        ->postJson($this->uri, ['agent_ids' => [$foreignAgent->external_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids.0']);

    expect($this->chatter->agents()->exists())->toBeFalse();
});
