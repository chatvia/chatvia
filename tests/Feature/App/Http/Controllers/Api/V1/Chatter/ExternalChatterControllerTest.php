<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * Creating a chatter through the public API: one with an email and a
 * password, keyed to the workspace and granted every agent the request
 * names. An email the workspace already uses (in any case) is a field error;
 * one agent that is deleted, not the workspace's, or that a calling
 * teammate's role does not reach refuses the whole request.
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

it('creates a chatter in the workspace granted every agent the request names', function (): void {
    $second = createAgent($this->owner);

    $body = $this->withToken($this->token)
        ->postJson('/api/v1/chatters', eccBody([$this->agent->external_id, $second->external_id]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Jane')
        ->json('data');

    $created = Chatter::where('external_id', $body['id'])->firstOrFail();
    expect($body['id'])->toBeUuid()
        ->and($created->workspace_id)->toBe($this->owner->workspace()->id)
        ->and($created->email)->toBe('jane@example.test')
        ->and($created->agents()->pluck('agents.id')->sort()->values()->all())->toBe(collect([$this->agent->id, $second->id])->sort()->values()->all())
        ->and(User::where('external_id', $body['id'])->exists())->toBeFalse();
});

it('refuses the whole request and creates no chatter', function (Closure $request, string $field): void {
    [$token, $body] = $request->call($this);

    $this->withToken($token)
        ->postJson('/api/v1/chatters', $body)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect(Chatter::query()->where('email', 'jane@example.test')->where('name', 'Jane')->exists())->toBeFalse();
})->with([
    'an email the workspace uses, in another case' => [function (): array {
        Chatter::factory()->ofWorkspace($this->owner)->create(['email' => 'jane@example.test', 'name' => 'Existing']);

        return [$this->token, eccBody([$this->agent->external_id], 'JANE@example.test')];
    }, 'email'],
    'a deleted agent' => [function (): array {
        $deleted = createAgent($this->owner);
        $deleted->delete();

        return [$this->token, eccBody([$deleted->external_id])];
    }, 'agent_ids.0'],
    "another workspace's agent" => [fn (): array => [$this->token, eccBody([$this->agent->external_id, createAgent(User::factory()->create())->external_id])], 'agent_ids.1'],
    "an agent the teammate's role does not reach" => [function (): array {
        $reached = createAgent($this->owner);
        $teammate = teammateWithPermissions($this->owner, ['account.chatters.manage'], allAgents: false, agentExternalIds: [$reached->external_id]);

        return [$teammate->createToken('account')->plainTextToken, eccBody([$reached->external_id, $this->agent->external_id])];
    }, 'agent_ids.1'],
]);
