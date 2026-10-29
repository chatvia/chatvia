<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's agents: creating one in the team team_id names (answering
 * with the agent alone, no agent token), refusing a body that names another
 * workspace's team, leaves the public choice out or mixes a library prompt
 * with custom instructions, and listing one team's agents.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    $this->actAsApiOwner();
});

/** A second team of the owner's workspace. */
function acSecondTeam(User $owner): Team
{
    return app(CreateTeamAction::class)->execute(new CreateTeamData(workspace: $owner->workspace(), name: 'Team B'));
}

it('creates the agent in the team team_id names and answers with the agent alone', function (): void {
    $team = acSecondTeam($this->owner);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/agents', ['name' => 'Team Bot', 'team_id' => $team->external_id, 'is_public' => false])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Team Bot')
        ->assertJsonPath('data.source', 'api');

    expect($response->json())->not->toHaveKey('token')
        ->and($team->agents()->where('name', 'Team Bot')->exists())->toBeTrue();
});

it('rejects a bad body and creates nothing', function (Closure $body, string $field): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/agents', $body($this->owner))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);

    expect($this->owner->workspace()->agents()->where('name', 'Rejected')->exists())->toBeFalse();
})->with([
    'no public choice' => [fn (User $owner): array => ['name' => 'Rejected', 'team_id' => $owner->primaryTeam()->external_id], 'is_public'],
    "another workspace's team" => [fn (): array => ['name' => 'Rejected', 'team_id' => ownerTeam(User::factory()->create())->external_id, 'is_public' => false], 'team_id'],
    'both a prompt and instructions' => [fn (User $owner): array => [
        'name' => 'Rejected',
        'team_id' => $owner->primaryTeam()->external_id,
        'prompt_id' => AgentPrompt::factory()->create(['workspace_id' => $owner->workspace()->id])->external_id,
        'system_instructions' => 'Custom prompt',
        'is_public' => false,
    ], 'prompt_id'],
]);

it('lists only the agents of the team team_id names and refuses another workspace\'s team', function (): void {
    $teamB = acSecondTeam($this->owner);
    makeTeamAgent($this->owner, 'In B', $teamB);

    $this->withToken($this->token)
        ->getJson('/api/v1/agents?team_id='.$teamB->external_id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'In B')
        ->assertJsonPath('data.0.team.id', $teamB->external_id);
    $this->withToken($this->token)
        ->getJson('/api/v1/agents?team_id='.createAgent(User::factory()->create())->team->external_id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id']);
});
