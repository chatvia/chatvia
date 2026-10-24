<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API's agents: creating one in a team with a library prompt or
 * custom instructions, listing them (optionally one team's), showing,
 * patching and deleting one. What the create and update Actions store is
 * tested with the Actions; this file checks the status, the field errors
 * and the answer.
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

describe('POST /api/v1/agents', function (): void {
    it('creates a knowledge-base agent and answers with the agent alone', function (): void {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/agents', ['name' => 'Support Bot', 'team_id' => $this->owner->primaryTeam()->external_id, 'is_public' => false])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Support Bot')
            ->assertJsonPath('data.source', 'api')
            ->assertJsonStructure(['data' => ['id', 'url', 'permanent_url']]);

        // The account key is the only key: no agent token comes back.
        expect($response->json())->not->toHaveKey('token');
    });

    it('creates the agent in the team team_id names', function (): void {
        $team = acSecondTeam($this->owner);

        $this->withToken($this->token)
            ->postJson('/api/v1/agents', ['name' => 'Team Bot', 'team_id' => $team->external_id, 'is_public' => false])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Team Bot');

        expect($team->agents()->where('name', 'Team Bot')->exists())->toBeTrue();
    });

    it('answers with the library prompt the agent was made with', function (): void {
        $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/agents', ['name' => 'Bot', 'team_id' => $this->owner->primaryTeam()->external_id, 'prompt_id' => $prompt->external_id, 'is_public' => false])
            ->assertCreated()
            ->assertJsonPath('data.prompt_id', $prompt->external_id)
            ->assertJsonPath('data.system_instructions', null);
    });

    it('answers with the custom instructions the agent was made with', function (): void {
        $this->withToken($this->token)
            ->postJson('/api/v1/agents', ['name' => 'Bot', 'team_id' => $this->owner->primaryTeam()->external_id, 'system_instructions' => 'Custom prompt', 'is_public' => false])
            ->assertCreated()
            ->assertJsonPath('data.system_instructions', 'Custom prompt')
            ->assertJsonPath('data.prompt_id', null);
    });

    it('rejects a body with a missing or bad field', function (Closure $body, string $field): void {
        $this->withToken($this->token)
            ->postJson('/api/v1/agents', $body($this->owner))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    })->with([
        'no team' => [fn (): array => ['name' => 'Bot'], 'team_id'],
        // Agents default to protected, so making one public must be said.
        'no public choice' => [fn (User $owner): array => ['name' => 'Bot', 'team_id' => $owner->primaryTeam()->external_id], 'is_public'],
        'a one-letter name' => [fn (User $owner): array => ['name' => 'x', 'team_id' => $owner->primaryTeam()->external_id], 'name'],
        "another workspace's team" => [fn (): array => ['name' => 'Bot', 'team_id' => ownerTeam(User::factory()->create())->external_id, 'is_public' => false], 'team_id'],
        'both a prompt and instructions' => [fn (User $owner): array => [
            'name' => 'Bot',
            'team_id' => $owner->primaryTeam()->external_id,
            'prompt_id' => AgentPrompt::factory()->create(['workspace_id' => $owner->workspace()->id])->external_id,
            'system_instructions' => 'Custom prompt',
        ], 'prompt_id'],
        'an unknown prompt' => [fn (User $owner): array => ['name' => 'Bot', 'team_id' => $owner->primaryTeam()->external_id, 'prompt_id' => 'does-not-exist'], 'prompt_id'],
    ]);
});

describe('GET /api/v1/agents', function (): void {
    it('lists the agents of every team, each with its team nested', function (): void {
        makeTeamAgent($this->owner, 'In B', acSecondTeam($this->owner));

        $body = $this->withToken($this->token)
            ->getJson('/api/v1/agents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->json('data');

        expect($body[0]['team']['id'])->toBeString()
            ->and($body[0]['team']['name'])->toBeString();
    });

    it('lists only the agents of the team team_id names', function (): void {
        $teamB = acSecondTeam($this->owner);
        makeTeamAgent($this->owner, 'In B', $teamB);

        $this->withToken($this->token)
            ->getJson('/api/v1/agents?team_id='.$teamB->external_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'In B')
            ->assertJsonPath('data.0.team.id', $teamB->external_id)
            ->assertJsonPath('data.0.team.name', 'Team B');
    });

    it('rejects a team filter that is not one of the caller\'s teams as a validation error', function (Closure $teamId): void {
        $this->withToken($this->token)
            ->getJson('/api/v1/agents?team_id='.$teamId())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id' => 'Team not found.']);
    })->with([
        'another workspace' => [fn (): string => createAgent(User::factory()->create())->team->external_id],
        'unknown team' => [fn (): string => '0198a5d4-3b2c-7f00-8000-000000000000'],
        'not a uuid' => [fn (): string => 'not-a-team'],
    ]);
});

describe('GET /api/v1/agents/{agent}', function (): void {
    it('answers with the agent', function (): void {
        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->agent->external_id)
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']]);
    });
});

describe('PATCH /api/v1/agents/{agent}', function (): void {
    it('patches the metadata and the public flag', function (): void {
        $this->withToken($this->token)
            ->patchJson("/api/v1/agents/{$this->agent->external_id}", ['metadata' => ['external_user_id' => 'cus_42'], 'is_public' => true])
            ->assertOk()
            ->assertJsonPath('data.metadata.external_user_id', 'cus_42')
            ->assertJsonPath('data.is_public', true);
    });

    it('swaps the agent between a library prompt and custom instructions', function (): void {
        $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $uri = "/api/v1/agents/{$this->agent->external_id}";

        $this->withToken($this->token)
            ->patchJson($uri, ['prompt_id' => $prompt->external_id])
            ->assertOk()
            ->assertJsonPath('data.prompt_id', $prompt->external_id)
            ->assertJsonPath('data.system_instructions', null);

        $this->withToken($this->token)
            ->patchJson($uri, ['system_instructions' => 'New custom'])
            ->assertOk()
            ->assertJsonPath('data.system_instructions', 'New custom')
            ->assertJsonPath('data.prompt_id', null);
    });
});

describe('DELETE /api/v1/agents/{agent}', function (): void {
    it('deletes the agent', function (): void {
        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertNoContent();

        expect(Agent::whereKey($this->agent->id)->exists())->toBeFalse();
    });
});
