<?php

use App\Identity\Chatter\Models\Chatter;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Granting a chatter more agents through the public API: a repeat grant
 * changes nothing, and an agent a teammate's team role does not reach is a
 * field error.
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

it('rejects an agent the calling teammate\'s role does not reach', function (): void {
    $reached = createAgent($this->owner);
    $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$reached->external_id]);
    $token = $teammate->createToken('account')->plainTextToken;

    $this->withToken($token)
        ->postJson($this->uri, ['agent_ids' => [$this->agent->external_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids.0']);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->postJson($this->uri, ['agent_ids' => [$reached->external_id]])->assertNoContent();

    expect($this->chatter->agents()->pluck('agents.id')->all())->toBe([$reached->id]);
});
