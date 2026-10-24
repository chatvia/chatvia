<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Illuminate\Support\Str;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Minting a sign-in link through the public API: a URL a chatter opens to
 * chat with one of the workspace's agents, signed in. Its token works until
 * it expires, at most a day. Another workspace's agent is a plain field
 * error.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
});

it('answers with a ready-to-open link whose token signs the chatter in on every use', function (): void {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id, 'ttl_seconds' => 600])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['url', 'expires_at']]);

    $url = $response->json('data.url');
    expect($url)->toContain('?token=');
    $token = Str::after($url, '?token=');

    // Read the secret minting provisioned on the agent, as a separate
    // request would, not the one this instance remembers from before.
    $this->agent->refresh();
    $service = app(AgentSignInTokenService::class);

    expect($service->verify($token, $this->agent)?->id)->toBe($this->chatter->id)
        ->and($service->verify($token, $this->agent)?->id)->toBe($this->chatter->id);
});

it('requires an agent_id and a user_id', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_id', 'user_id']);
});

it('takes a lifetime of one day', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id, 'ttl_seconds' => 86400])
        ->assertCreated();
});

it('rejects a lifetime longer than one day', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id, 'ttl_seconds' => 86401])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ttl_seconds']);
});

it("rejects another workspace's agent as a plain field error", function (): void {
    $otherAgent = createAgent(User::factory()->create());

    $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', ['agent_id' => $otherAgent->external_id, 'user_id' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_id', 'user_id']);
});
