<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\AgentSignInTokens;
use Illuminate\Support\Str;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Minting a sign-in link through the public API: a URL a chatter opens to
 * chat with one of the workspace's agents, signed in. Its token works until
 * it expires, at most a day. Another workspace's agent, an agent the calling
 * teammate's role does not reach and a chatter without access are field
 * errors, and a teammate's key never signs in the owner or another teammate.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
    $this->chatter = Chatter::factory()->grantedTo($this->agent)->create();
});

it('answers with a ready-to-open link of up to a day whose token signs the chatter in on every use', function (): void {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id, 'ttl_seconds' => 86400])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['url', 'expires_at']]);

    $url = $response->json('data.url');
    $token = Str::after($url, '?token=');
    $service = app(AgentSignInTokens::class);
    $this->agent->refresh();
    expect($url)->toContain('?token=')
        ->and($service->verify($token, $this->agent)?->id)->toBe($this->chatter->id)
        ->and($service->verify($token, $this->agent)?->id)->toBe($this->chatter->id);
});

it('refuses a link that would sign in the wrong person as a plain field error', function (Closure $request, array $fields): void {
    [$token, $body] = $request->call($this);

    $this->withToken($token)
        ->postJson('/api/v1/chatters/sign', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);
})->with([
    'a lifetime longer than a day' => [fn (): array => [$this->token, ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id, 'ttl_seconds' => 86401]], ['ttl_seconds']],
    "another workspace's agent" => [fn (): array => [$this->token, ['agent_id' => createAgent(User::factory()->create())->external_id, 'user_id' => 'x']], ['agent_id', 'user_id']],
    'an agent the teammate\'s role does not reach' => [fn (): array => [
        teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [createAgent($this->owner, ['name' => 'Reached'])->external_id])->createToken('account')->plainTextToken,
        ['agent_id' => $this->agent->external_id, 'user_id' => $this->chatter->external_id],
    ], ['agent_id']],
    'a chatter without access to the agent' => [fn (): array => [$this->token, ['agent_id' => $this->agent->external_id, 'user_id' => Chatter::factory()->create(['workspace_id' => $this->agent->workspace_id])->external_id]], ['user_id']],
]);

it("refuses a teammate's key a link that signs in the owner or another teammate, but the owner's key may sign in a teammate", function (): void {
    $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage', 'agent.chat.view']);
    $otherTeammate = teammateWithPermissions($this->owner, ['agent.chat.view']);
    $teammateToken = $teammate->createToken('account')->plainTextToken;
    $sign = fn (string $token, string $userId) => $this->withToken($token)->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $userId]);

    $sign($teammateToken, $this->owner->external_id)->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
    $sign($teammateToken, $otherTeammate->external_id)->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
    $sign($teammateToken, $teammate->external_id)->assertCreated();
    $sign($teammateToken, $this->chatter->external_id)->assertCreated();
    $this->app['auth']->forgetGuards();
    $sign($this->token, $otherTeammate->external_id)->assertCreated();
});
