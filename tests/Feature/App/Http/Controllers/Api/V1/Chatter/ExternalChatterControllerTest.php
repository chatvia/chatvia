<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Creating a chatter through the public API: one with an email and a
 * password, granted the agents the request names. An email the workspace
 * already uses is a field error; which other emails are allowed is
 * CreateChatterAction's rule and is tested there. One agent that is not the
 * workspace's refuses the whole request.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

/**
 * A complete create body for the given agents.
 *
 * @param  list<string>  $agentIds
 * @return array<string, mixed>
 */
function eccBody(array $agentIds, string $email = 'jane@example.test'): array
{
    return ['name' => 'Jane', 'agent_ids' => $agentIds, 'email' => $email, 'password' => 'super-secret-pw'];
}

it('creates a chatter granted to the agent and answers with its public id', function (): void {
    $body = $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$this->agent->external_id]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Jane')
        ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
        ->json('data');

    $created = Chatter::where('external_id', $body['id'])->firstOrFail();

    expect($body['id'])->toBeUuid()
        ->and($created->workspace_id)->toBe($this->owner->workspace()->id)
        ->and($created->email)->toBe('jane@example.test')
        ->and($created->canAccessAgent($this->agent))->toBeTrue()
        ->and(User::where('external_id', $body['id'])->exists())->toBeFalse();
});

it('grants the chatter every agent the request names', function (): void {
    $second = createAgent($this->owner);

    $body = $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$this->agent->external_id, $second->external_id], 'multi@example.test'))
        ->assertCreated()
        ->json('data');

    expect(Chatter::where('external_id', $body['id'])->firstOrFail()->agents()->pluck('agents.id')->all())
        ->toHaveCount(2)
        ->toContain($this->agent->id)
        ->toContain($second->id);
});

it('answers an email the workspace already uses for a chatter, in any case, with a field error', function (): void {
    Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'jane@example.test']);

    $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$this->agent->external_id], 'JANE@example.test'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($this->owner->workspace()->chatters()->whereRaw('lower(email) = ?', ['jane@example.test'])->count())->toBe(1);
});

it('rejects a body without a required field', function (array $body, array $fields): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/chatters', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($fields);
})->with([
    'no name' => [['agent_ids' => ['x'], 'email' => 'jane@example.test', 'password' => 'super-secret-pw'], ['name']],
    'no agent ids' => [['name' => 'Jane', 'email' => 'jane@example.test', 'password' => 'super-secret-pw'], ['agent_ids']],
    'no email or password' => [['name' => 'Jane', 'agent_ids' => ['x']], ['email', 'password']],
]);

it('rejects a deleted agent', function (): void {
    $deleted = createAgent($this->owner);
    $deleted->delete();

    $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$deleted->external_id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids.0']);
});

it("refuses the whole request when one agent is another workspace's and creates no chatter", function (): void {
    $foreignAgent = createAgent(User::factory()->create());

    $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$this->agent->external_id, $foreignAgent->external_id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['agent_ids.1'])
        ->assertJsonMissingValidationErrors(['agent_ids.0']);

    expect(Chatter::where('email', 'jane@example.test')->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeFalse();
});
