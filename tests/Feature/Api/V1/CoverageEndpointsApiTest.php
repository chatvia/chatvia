<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

/*
|--------------------------------------------------------------------------
| Public API — the endpoints that round out existing areas
|--------------------------------------------------------------------------
|
| Teams (show, delete, role delete, member removal), the users list, chatter
| reads and access controls, Q&A sources, custom MCP creation and the prompt
| library. Permission and paging behaviour is covered by ApiPermissionTest
| and ApiListPaginationTest; this file covers the endpoints' own rules.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->agent = createAgent($this->owner);
    $this->token = $this->owner->createToken('account')->plainTextToken;
});

describe('teams', function (): void {
    it('shows a team with its roles and agents nested', function (): void {
        $this->withToken($this->token)
            ->getJson('/api/v1/teams/'.ownerTeam($this->owner)->external_id)
            ->assertOk()
            ->assertJsonPath('data.id', ownerTeam($this->owner)->external_id)
            ->assertJsonStructure(['data' => ['roles', 'agents']]);
    });

    it('deletes a spare empty team, and refuses the primary one with a field error', function (): void {
        $spare = app(CreateTeamAction::class)->execute(new CreateTeamData($this->owner->workspace(), 'Spare'));

        $this->withToken($this->token)->deleteJson("/api/v1/teams/{$spare->external_id}")->assertNoContent();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/teams/'.ownerTeam($this->owner)->external_id)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team']);
    });

    it('refuses a team that still has members or a trashed agent, with a field error', function (): void {
        $staffed = app(CreateTeamAction::class)->execute(new CreateTeamData($this->owner->workspace(), 'Staffed'));
        $member = User::factory()->employee($this->owner)->create();
        $staffed->members()->attach($member->id, ['role_id' => $staffed->roles()->value('id')]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$staffed->external_id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team' => __('You cannot delete a team that has members. Please remove its members first.')]);

        $purging = app(CreateTeamAction::class)->execute(new CreateTeamData($this->owner->workspace(), 'Purging'));
        createAgent($this->owner, ['team_id' => $purging->id])->delete();

        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$purging->external_id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['team' => __('You cannot delete a team that has agents. Please delete or move all agents first.')]);

        expect($staffed->fresh())->not->toBeNull()
            ->and($purging->fresh())->not->toBeNull();
    });

    it('deletes an empty role and refuses one that members still hold', function (): void {
        $team = ownerTeam($this->owner);
        $empty = $team->roles()->create(['name' => 'Ghost role']);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$team->external_id}/roles/{$empty->external_id}")
            ->assertNoContent();

        $held = $team->roles()->create(['name' => 'Held role']);
        $member = User::factory()->employee($this->owner)->create();
        $team->members()->attach($member->id, ['role_id' => $held->id]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$team->external_id}/roles/{$held->external_id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    });

    it('removes a member from the team but never the owner', function (): void {
        $team = ownerTeam($this->owner);
        $member = User::factory()->employee($this->owner)->create();
        $team->members()->attach($member->id, ['role_id' => $team->roles()->value('id')]);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$team->external_id}/members/{$member->external_id}")
            ->assertNoContent();

        expect($team->members()->whereKey($member->id)->exists())->toBeFalse()
            ->and($member->fresh())->not->toBeNull();

        // The owner holds the team rather than a membership, so the scoped
        // {member} binding never resolves them: 404, not removable.
        $this->withToken($this->token)
            ->deleteJson("/api/v1/teams/{$team->external_id}/members/{$this->owner->external_id}")
            ->assertNotFound();
    });

    it('404s removing someone who is not on the team', function (): void {
        $outsider = User::factory()->employee($this->owner)->create();

        $this->withToken($this->token)
            ->deleteJson('/api/v1/teams/'.ownerTeam($this->owner)->external_id."/members/{$outsider->external_id}")
            ->assertNotFound();
    });

    it('lists the workspace users with their email', function (): void {
        $this->withToken($this->token)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->owner->external_id)
            ->assertJsonPath('data.0.email', $this->owner->email);
    });

    it('keeps the users list owner-only', function (): void {
        $teammate = teammateWithPermissions($this->owner, []);

        withApiToken($teammate->createToken('t')->plainTextToken)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    });
});

describe('chatters', function (): void {
    it('shows a chatter with its access controls', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);

        $this->withToken($this->token)
            ->getJson("/api/v1/chatters/{$chatter->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $chatter->external_id)
            ->assertJsonPath('data.deactivated', false)
            ->assertJsonPath('data.messages_limit', null);
    });

    it('sets and clears the monthly cap and flips the deactivation switch', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $uri = "/api/v1/chatters/{$chatter->external_id}";

        $this->withToken($this->token)
            ->patchJson($uri, ['messages_limit' => 50, 'deactivated' => true, 'deactivation_reason' => 'Trial over'])
            ->assertOk()
            ->assertJsonPath('data.messages_limit', 50)
            ->assertJsonPath('data.deactivated', true);

        expect($chatter->fresh()->deactivation_reason)->toBe('Trial over');

        // A field left out keeps its value; null clears the cap.
        $this->withToken($this->token)
            ->patchJson($uri, ['messages_limit' => null])
            ->assertOk()
            ->assertJsonPath('data.messages_limit', null)
            ->assertJsonPath('data.deactivated', true);

        $this->withToken($this->token)
            ->patchJson($uri, ['deactivated' => false])
            ->assertOk()
            ->assertJsonPath('data.deactivated', false);
    });

    it('grants agents idempotently, and the grant survives a repeat', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $grant = fn () => $this->withToken($this->token)
            ->postJson("/api/v1/chatters/{$chatter->external_id}/agents", ['agent_ids' => [$this->agent->external_id]]);

        $grant()->assertNoContent();
        $grant()->assertNoContent();

        expect($chatter->agents()->whereKey($this->agent->id)->count())->toBe(1);
    });

    it('rejects granting another workspace\'s agent', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $foreignAgent = createAgent(User::factory()->create());

        $this->withToken($this->token)
            ->postJson("/api/v1/chatters/{$chatter->external_id}/agents", ['agent_ids' => [$foreignAgent->external_id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['agent_ids.0']);
    });
});

describe('Q&A sources', function (): void {
    it('adds and deletes a Q&A entry', function (): void {
        Queue::fake();

        $created = $this->withToken($this->token)
            ->postJson("/api/v1/agents/{$this->agent->external_id}/sources/qas", [
                'title' => 'Refunds',
                'questions' => ['Can I get a refund?', 'How do refunds work?'],
                'answer' => 'Refunds are available within 30 days of purchase.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Refunds')
            ->assertJsonPath('data.questions.1', 'How do refunds work?');

        $id = $created->json('data.id');
        expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->count())->toBe(1);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}/sources/qas/{$id}")
            ->assertNoContent();

        expect(AgentQuestionAnswer::query()->where('agent_id', $this->agent->id)->exists())->toBeFalse();
    });

    it('404s a Q&A entry of another agent', function (): void {
        $other = createAgent($this->owner);
        $qa = AgentQuestionAnswer::create(['agent_id' => $other->id, 'title' => 'T', 'questions' => 'Q?', 'answer' => 'A long enough answer.']);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}/sources/qas/{$qa->external_id}")
            ->assertNotFound();
    });
});

describe('connectors', function (): void {
    it('adds a custom MCP server and reports kind on every row', function (): void {
        Queue::fake();
        $this->mock(McpClient::class, fn (MockInterface $mock) => $mock->shouldReceive('fetchTools')->andReturn([]));

        $this->withToken($this->token)
            ->postJson('/api/v1/mcps', [
                'name' => 'Internal tools',
                'url' => 'https://mcp.example.test/stream',
                'auth_strategy' => 'token',
                'token' => 'secret-token',
            ])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'custom')
            ->assertJsonPath('data.has_auth_value', true)
            ->assertJsonMissingPath('data.auth_value');

        AgentMcpServer::factory()->create([
            'workspace_id' => $this->owner->workspace()->id,
            'category' => McpServerCategory::Integration,
            'connector_slug' => 'slack',
        ]);

        $list = $this->withToken($this->token)->getJson('/api/v1/mcps')->assertOk();
        expect(collect($list->json('data'))->pluck('kind')->sort()->values()->all())->toBe(['catalog', 'custom']);
    });

    it('refuses deleting a catalog connector', function (): void {
        $catalog = AgentMcpServer::factory()->create([
            'workspace_id' => $this->owner->workspace()->id,
            'category' => McpServerCategory::Integration,
            'connector_slug' => 'slack',
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/mcps/{$catalog->external_id}")->assertNotFound();
    });
});

describe('audit regressions', function (): void {
    it('answers 422, not 500, for a malformed role user_id', function (): void {
        $team = ownerTeam($this->owner);
        $role = $team->roles()->create(['name' => 'Support']);

        $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$team->external_id}/roles/{$role->external_id}/users", ['user_id' => 'not-a-uuid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    });

    it('forbids a teammate from updating a team', function (): void {
        $teammate = teammateWithPermissions($this->owner, []);

        withApiToken($teammate->createToken('t')->plainTextToken)
            ->patchJson('/api/v1/teams/'.ownerTeam($this->owner)->external_id, ['name' => 'Hijacked'])
            ->assertForbidden();

        expect(ownerTeam($this->owner)->fresh()->name)->not->toBe('Hijacked');
    });

    it('forbids minting sign-in links without account.chatters.manage', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $teammate = teammateWithPermissions($this->owner, []);

        withApiToken($teammate->createToken('t')->plainTextToken)
            ->postJson('/api/v1/chatters/sign', ['agent_id' => $this->agent->external_id, 'user_id' => $chatter->external_id])
            ->assertForbidden();
    });

    it('accepts a sign-in link ttl up to one day', function (): void {
        $chatter = Chatter::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
        $chatter->agents()->attach($this->agent->id, ['workspace_id' => $this->agent->workspace_id]);

        $this->withToken($this->token)
            ->postJson('/api/v1/chatters/sign', [
                'agent_id' => $this->agent->external_id,
                'user_id' => $chatter->external_id,
                'ttl_seconds' => 86400,
            ])
            ->assertCreated();
    });

    it('updates a role partially: an omitted field keeps its value', function (): void {
        $team = ownerTeam($this->owner);
        $create = $this->withToken($this->token)
            ->postJson("/api/v1/teams/{$team->external_id}/roles", [
                'name' => 'Analysts',
                'agent_ids' => [$this->agent->external_id],
                'permissions' => ['agent.chat.view'],
            ])
            ->assertCreated();

        $this->withToken($this->token)
            ->patchJson("/api/v1/teams/{$team->external_id}/roles/".$create->json('data.id'), ['name' => 'Analysts v2'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Analysts v2')
            ->assertJsonPath('data.agent_ids.0', $this->agent->external_id)
            ->assertJsonPath('data.permissions.0', 'agent.chat.view');
    });

    it('returns the agent shape on show', function (): void {
        $this->withToken($this->token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->agent->external_id)
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']]);
    });
});

describe('prompts', function (): void {
    it('lists prompts for a teammate holding account.prompts and hides them from one without', function (): void {
        $prompt = $this->owner->workspace()->prompts()->create(['name' => 'Tone', 'prompt' => 'Be concise.']);

        $holder = teammateWithPermissions($this->owner, ['account.prompts']);
        withApiToken($holder->createToken('t')->plainTextToken)
            ->getJson('/api/v1/prompts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $prompt->external_id);

        $teammate = teammateWithPermissions($this->owner, []);
        withApiToken($teammate->createToken('t')->plainTextToken)
            ->getJson('/api/v1/prompts')
            ->assertNotFound();
    });
});
