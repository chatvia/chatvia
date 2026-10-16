<?php

use App\Identity\Chatter\Actions\CreateChatterAction;
use App\Identity\Chatter\Data\CreateChatterData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Public API — permission parity
|--------------------------------------------------------------------------
|
| The public API enforces the SAME permission model as the dashboard:
|   • the workspace owner has full access (owners bypass every gate);
|   • a teammate is limited to exactly the permissions their role grants;
|   • a teammate WITHOUT the relevant permission can never issue the request.
|
| Every test contrasts owner / member-without / member-with so the gate under
| test is the permission — not tenancy or agent access. Another workspace's
| records are covered by tests/Feature/Isolation.
*/

beforeEach(function (): void {
    // Deleting a chatter wipes its avatar tree on the assets disk and its
    // attachment files on the private disk; fake both so the delete tests
    // stay hermetic and never reach real S3.
    Storage::fake('private');
    Storage::fake('assets');

    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->ownerToken = $this->owner->createToken('account')->plainTextToken;
    $this->agent = createAgent($this->owner);
});

/**
 * A bearer token of a teammate of $owner whose role grants exactly
 * $permissions (all agents unless $agentIds lists the ones it reaches).
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentIds  external ids when $allAgents is false
 */
function permMemberToken(User $owner, array $permissions, bool $allAgents = true, array $agentIds = []): string
{
    return teammateWithPermissions($owner, $permissions, allAgents: $allAgents, agentExternalIds: $agentIds)
        ->createToken('account')->plainTextToken;
}

// ---------------------------------------------------------------------------
// Account-level resources — gated by team.permission:account.*
// ---------------------------------------------------------------------------

describe('account-level functions (account.functions)', function (): void {

    it('lets the owner read and the gate is open to write', function (): void {
        withApiToken($this->ownerToken)->getJson('/api/v1/functions')->assertOk();
        // owner passes the gate, then hits validation on an empty body
        withApiToken($this->ownerToken)->postJson('/api/v1/functions', [])->assertStatus(422);
    });

    it('forbids a member WITHOUT account.functions (read AND write)', function (): void {
        $token = permMemberToken($this->owner, []);

        withApiToken($token)->getJson('/api/v1/functions')->assertForbidden();
        withApiToken($token)->postJson('/api/v1/functions', [
            'internal_name' => 'x', 'description' => 'y', 'webhook_url' => 'https://e.test/x',
        ])->assertForbidden();
    });

    it('lets a member WITH account.functions through the gate', function (): void {
        $token = permMemberToken($this->owner, ['account.functions']);

        withApiToken($token)->getJson('/api/v1/functions')->assertOk();
        // passes the gate, then fails validation — proves the 403 is gone
        withApiToken($token)->postJson('/api/v1/functions', [])->assertStatus(422);
    });
});

describe('account-level MCP servers (account.connectors)', function (): void {

    it('lets the owner list and delete', function (): void {
        $mcp = AgentMcpServer::factory()->forOwner($this->owner)->create();

        withApiToken($this->ownerToken)->getJson('/api/v1/mcps')->assertOk();
        withApiToken($this->ownerToken)->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertNoContent();
    });

    it('forbids a member WITHOUT account.connectors (list AND delete)', function (): void {
        $mcp = AgentMcpServer::factory()->forOwner($this->owner)->create();
        $token = permMemberToken($this->owner, []);

        withApiToken($token)->getJson('/api/v1/mcps')->assertForbidden();
        withApiToken($token)->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertForbidden();

        expect(AgentMcpServer::whereKey($mcp->id)->exists())->toBeTrue();
    });

    it('lets a member WITH account.connectors through the gate', function (): void {
        $mcp = AgentMcpServer::factory()->forOwner($this->owner)->create();
        $token = permMemberToken($this->owner, ['account.connectors']);

        withApiToken($token)->getJson('/api/v1/mcps')->assertOk();
        withApiToken($token)->deleteJson("/api/v1/mcps/{$mcp->external_id}")->assertNoContent();
    });
});

describe('account-level chatters (account.chatters.manage)', function (): void {

    $chatterBody = fn (Agent $agent): array => [
        'name' => 'Jane', 'agent_ids' => [$agent->external_id],
        'email' => 'jane'.Str::random(6).'@example.test', 'password' => 'super-secret-pw',
    ];

    it('lets the owner create a chatter', function () use ($chatterBody): void {
        withApiToken($this->ownerToken)
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertCreated();
    });

    it('forbids a member WITHOUT account.chatters.manage', function () use ($chatterBody): void {
        $token = permMemberToken($this->owner, []);

        withApiToken($token)
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertForbidden();
    });

    it('lets a member WITH account.chatters.manage create a chatter, keyed to the owner workspace', function () use ($chatterBody): void {
        $token = permMemberToken($this->owner, ['account.chatters.manage']);

        $id = withApiToken($token)
            ->postJson('/api/v1/chatters', $chatterBody($this->agent))
            ->assertCreated()
            ->json('data.id');

        expect(Chatter::where('external_id', $id)->value('workspace_id'))->toBe($this->owner->workspace()->id);
    });

    it('forbids a member WITHOUT account.chatters.manage from deleting a chatter', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, []);

        withApiToken($token)
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertForbidden();

        expect(Chatter::whereKey($chatter->id)->exists())->toBeTrue();
    });

    it('lets a member WITH account.chatters.manage delete a chatter', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, ['account.chatters.manage']);

        withApiToken($token)
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id)
            ->assertNoContent();

        expect(Chatter::whereKey($chatter->id)->exists())->toBeFalse();
    });

    it('forbids a member WITHOUT account.chatters.manage from revoking agent access', function (): void {
        $chatter = app(CreateChatterAction::class)->execute(new CreateChatterData($this->owner->workspace(), $this->agent, 'Jane'));
        $token = permMemberToken($this->owner, []);

        withApiToken($token)
            ->deleteJson('/api/v1/chatters/'.$chatter->external_id.'/agents', ['agent_ids' => [$this->agent->external_id]])
            ->assertForbidden();

        expect($chatter->canAccessAgent($this->agent))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Owner-only operations — gated by api.owner (no delegable permission)
// ---------------------------------------------------------------------------

describe('owner-only operations (api.owner)', function (): void {

    it('forbids a member from creating an agent — even with broad agent perms', function (): void {
        $token = permMemberToken($this->owner, ['agent.setup', 'agent.delete']);

        withApiToken($token)
            ->postJson('/api/v1/agents', ['name' => 'Sneaky', 'team_id' => $this->owner->primaryTeam()->external_id, 'is_public' => false])
            ->assertForbidden();
    });

    it('lets the owner create an agent', function (): void {
        withApiToken($this->ownerToken)
            ->postJson('/api/v1/agents', ['name' => 'Support Bot', 'team_id' => $this->owner->primaryTeam()->external_id, 'is_public' => false])
            ->assertCreated();
    });

    it('forbids a member from listing or creating teams', function (): void {
        $token = permMemberToken($this->owner, ['account.chatters.manage', 'agent.setup']);

        withApiToken($token)->getJson('/api/v1/teams')->assertForbidden();
        withApiToken($token)->postJson('/api/v1/teams', ['name' => 'X'])->assertForbidden();
    });

    it('lets the owner list teams', function (): void {
        withApiToken($this->ownerToken)->getJson('/api/v1/teams')->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Per-agent operations — gated by can.access.agent + agent.permission:*
// ---------------------------------------------------------------------------

describe('per-agent: update is gated by agent.setup', function (): void {

    it('owner and member-with pass; member-without is forbidden', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}";

        withApiToken(permMemberToken($this->owner, []))
            ->patchJson($uri, ['is_public' => true])->assertForbidden();

        withApiToken(permMemberToken($this->owner, ['agent.setup']))
            ->patchJson($uri, ['is_public' => true])->assertOk();

        withApiToken($this->ownerToken)
            ->patchJson($uri, ['is_public' => true])->assertOk();
    });
});

describe('per-agent: delete is gated by agent.delete', function (): void {

    it('forbids a member who has agent.setup but not agent.delete', function (): void {
        // The contrast with agent.setup proves it's specifically agent.delete.
        $token = permMemberToken($this->owner, ['agent.setup']);

        withApiToken($token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertForbidden();

        expect(Agent::whereKey($this->agent->id)->exists())->toBeTrue();
    });

    it('lets a member WITH agent.delete delete the agent', function (): void {
        $token = permMemberToken($this->owner, ['agent.delete']);

        withApiToken($token)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertNoContent();

        expect(Agent::whereKey($this->agent->id)->exists())->toBeFalse();
    });

    it('lets the owner delete the agent', function (): void {
        withApiToken($this->ownerToken)
            ->deleteJson("/api/v1/agents/{$this->agent->external_id}")
            ->assertNoContent();
    });
});

describe('per-agent: training + chat are gated by their permission', function (): void {

    it('gates file training by agent.sources.train.files', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/sources/files";

        withApiToken(permMemberToken($this->owner, []))
            ->postJson($uri, [])->assertForbidden();

        // with the permission the gate opens; the empty body then fails validation
        withApiToken(permMemberToken($this->owner, ['agent.sources.train.files']))
            ->postJson($uri, [])->assertStatus(422);
    });

    it('gates MCP attach by agent.sources.train.connectors', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/mcps";

        withApiToken(permMemberToken($this->owner, []))
            ->postJson($uri, [])->assertForbidden();

        withApiToken(permMemberToken($this->owner, ['agent.sources.train.connectors']))
            ->postJson($uri, [])->assertStatus(422);
    });

    it('gates conversation history by agent.chat.view', function (): void {
        $uri = "/api/v1/agents/{$this->agent->external_id}/conversations";

        withApiToken(permMemberToken($this->owner, []))
            ->getJson($uri)->assertForbidden();

        withApiToken(permMemberToken($this->owner, ['agent.chat.view']))
            ->getJson($uri)->assertOk();

        withApiToken($this->ownerToken)->getJson($uri)->assertOk();
    });

    it('lets any member who can access the agent read it (no extra permission)', function (): void {
        // Reads (show, source list) need access but no specific permission —
        // matching the dashboard's open agent read pages.
        $token = permMemberToken($this->owner, []);

        withApiToken($token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}")->assertOk();
        withApiToken($token)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/sources")->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Agent-access isolation — gated by can.access.agent
// ---------------------------------------------------------------------------

describe('agent access isolation', function (): void {

    it('forbids a member from an agent their role does not grant', function (): void {
        $granted = $this->agent;
        $ungranted = createAgent($this->owner, ['name' => 'Other']);

        // Access to ONLY the granted agent, with the chat permission.
        $token = permMemberToken($this->owner, ['agent.chat.view'], allAgents: false, agentIds: [$granted->external_id]);

        withApiToken($token)
            ->getJson("/api/v1/agents/{$granted->external_id}/conversations")->assertOk();

        // Same permission, but no access to this agent → blocked at can.access.agent.
        withApiToken($token)
            ->getJson("/api/v1/agents/{$ungranted->external_id}/conversations")->assertForbidden();
        withApiToken($token)
            ->getJson("/api/v1/agents/{$ungranted->external_id}")->assertForbidden();
    });
});

describe('per-agent: every other mutating route is gated by its own permission', function (): void {

    beforeEach(function (): void {
        Storage::fake();
        Bus::fake();
    });

    it('forbids a member without the permission and lets one with it through', function (string $method, string $permission, int $allowed, Closure $request): void {
        // Every other agent permission, so only the one under test is missing.
        $others = array_values(array_filter(
            array_keys(config('team-permissions.permissions')),
            fn (string $code): bool => str_starts_with($code, 'agent.') && $code !== $permission,
        ));

        [$uri, $body, $untouched] = $request($this->agent);

        withApiToken(permMemberToken($this->owner, $others))
            ->json($method, "/api/v1/agents/{$this->agent->external_id}/{$uri}", $body)
            ->assertForbidden();

        expect($untouched())->toBeTrue();

        // Through the gate: done, or refused by validation for an empty body.
        withApiToken(permMemberToken($this->owner, [$permission]))
            ->json($method, "/api/v1/agents/{$this->agent->external_id}/{$uri}", $body)
            ->assertStatus($allowed);
    })->with([
        'add a text' => ['POST', 'agent.sources.train.text', 422, fn (Agent $agent): array => [
            'sources/texts', [], fn (): bool => $agent->texts()->doesntExist(),
        ]],
        'add a web page' => ['POST', 'agent.sources.train.web', 422, fn (Agent $agent): array => [
            'sources/urls', [], fn (): bool => $agent->websites()->doesntExist(),
        ]],
        'delete a file' => ['DELETE', 'agent.sources.delete', 204, function (Agent $agent): array {
            $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
            $agent->files()->attach($file->id);

            return ["sources/files/{$file->external_id}", [], fn (): bool => $agent->files()->whereKey($file->id)->exists()];
        }],
        'delete a text' => ['DELETE', 'agent.sources.delete', 204, function (Agent $agent): array {
            $text = SourceText::query()->create([
                'workspace_id' => $agent->workspace_id,
                'filepath' => 'workspaces/test/texts/'.Str::uuid()->toString().'.txt',
                'excerpt' => 'Our refund policy',
                'length' => 1,
                'checksum' => md5(Str::uuid()->toString()),
            ]);
            $agent->texts()->attach($text->id);

            return ["sources/texts/{$text->external_id}", [], fn (): bool => $agent->texts()->whereKey($text->id)->exists()];
        }],
        'delete a web page' => ['DELETE', 'agent.sources.delete', 204, function (Agent $agent): array {
            $website = SourceWebsite::query()->create(['workspace_id' => $agent->workspace_id, 'url' => 'https://docs.example.test/', 'status' => 'ingested']);
            $agent->websites()->attach($website->id);

            return ["sources/urls/{$website->external_id}", [], fn (): bool => $agent->websites()->whereKey($website->id)->exists()];
        }],
        'detach an MCP server' => ['DELETE', 'agent.sources.train.connectors', 204, function (Agent $agent): array {
            $server = AgentMcpServer::factory()->attachedTo($agent)->create();

            return ["mcps/{$server->external_id}", [], fn (): bool => $agent->mcpServers()->whereKey($server->id)->exists()];
        }],
        'attach a function' => ['POST', 'agent.sources.train.functions', 200, function (Agent $agent): array {
            $function = AgentFunction::factory()->create(['workspace_id' => $agent->workspace_id]);

            return ['functions', ['function_id' => $function->external_id], fn (): bool => $agent->functions()->doesntExist()];
        }],
        'detach a function' => ['DELETE', 'agent.sources.train.functions', 204, function (Agent $agent): array {
            $function = AgentFunction::factory()->attachedTo($agent)->create();

            return ["functions/{$function->external_id}", [], fn (): bool => $agent->functions()->whereKey($function->id)->exists()];
        }],
        'add a Q&A' => ['POST', 'agent.sources.train.qa', 422, fn (Agent $agent): array => [
            'sources/qas', [], fn (): bool => AgentQuestionAnswer::query()->where('agent_id', $agent->id)->doesntExist(),
        ]],
        'delete a Q&A' => ['DELETE', 'agent.sources.delete', 204, function (Agent $agent): array {
            $qa = AgentQuestionAnswer::create(['agent_id' => $agent->id, 'title' => 'Refunds', 'questions' => 'Can I get a refund?', 'answer' => 'Within 30 days, yes.']);

            return ["sources/qas/{$qa->external_id}", [], fn (): bool => AgentQuestionAnswer::query()->whereKey($qa->id)->exists()];
        }],
        'delete a conversation' => ['DELETE', 'agent.chat.delete', 204, function (Agent $agent): array {
            $session = Session::factory()->create(['agent_id' => $agent->id]);
            $conversation = Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id]);

            return ["conversations/{$conversation->external_id}", [], fn (): bool => Conversation::query()->whereKey($conversation->id)->exists()];
        }],
        'create a form' => ['POST', 'agent.sources.train.connectors', 422, fn (Agent $agent): array => [
            'forms', [], fn (): bool => FormDefinition::query()->forAgent($agent)->doesntExist(),
        ]],
        'update a form' => ['PATCH', 'agent.sources.train.connectors', 200, function (Agent $agent): array {
            $form = FormDefinition::factory()->forAgent($agent)->create(['name' => 'Original']);

            return ["forms/{$form->external_id}", ['name' => 'Renamed'], fn (): bool => $form->fresh()->name === 'Original'];
        }],
        'delete a form' => ['DELETE', 'agent.sources.train.connectors', 204, function (Agent $agent): array {
            $form = FormDefinition::factory()->forAgent($agent)->create();

            return ["forms/{$form->external_id}", [], fn (): bool => FormDefinition::query()->whereKey($form->id)->exists()];
        }],
        'trigger an eval run' => ['POST', 'agent.evals.manage', 201, fn (Agent $agent): array => [
            'evals/runs', [], fn (): bool => EvalRun::query()->forAgent($agent->id)->doesntExist(),
        ]],
        'create a topic' => ['POST', 'agent.topics.manage', 422, fn (Agent $agent): array => [
            'topics', [], fn (): bool => Topic::query()->where('workspace_id', $agent->workspace_id)->doesntExist(),
        ]],
        'rename a topic' => ['PATCH', 'agent.topics.manage', 422, function (Agent $agent): array {
            $topic = Topic::query()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Shipping', 'description' => 'Delivery questions']);
            $agent->topics()->attach($topic->id);

            return ["topics/{$topic->external_id}", [], fn (): bool => $topic->fresh()->topic === 'Shipping'];
        }],
        'detach a topic' => ['DELETE', 'agent.topics.manage', 204, function (Agent $agent): array {
            $topic = Topic::query()->create(['workspace_id' => $agent->workspace_id, 'topic' => 'Returns', 'description' => 'Return questions']);
            $agent->topics()->attach($topic->id);

            return ["topics/{$topic->external_id}", [], fn (): bool => $agent->topics()->where('topics.id', $topic->id)->exists()];
        }],
    ]);
});

// ---------------------------------------------------------------------------
// Per-agent reads — each gated by its own permission
// ---------------------------------------------------------------------------

describe('per-agent: every read endpoint is gated by its own permission', function (): void {

    it('forbids a member without the permission and lets one with it through', function (string $permission, Closure $request): void {
        $uri = $request($this->agent);

        withApiToken(permMemberToken($this->owner, []))
            ->getJson("/api/v1/agents/{$this->agent->external_id}/{$uri}")
            ->assertForbidden();

        withApiToken(permMemberToken($this->owner, [$permission]))
            ->getJson("/api/v1/agents/{$this->agent->external_id}/{$uri}")
            ->assertOk();

        withApiToken($this->ownerToken)
            ->getJson("/api/v1/agents/{$this->agent->external_id}/{$uri}")
            ->assertOk();
    })->with([
        'list submissions (agent.leads.view)' => ['agent.leads.view', function (Agent $agent): string {
            $form = FormDefinition::factory()->forAgent($agent)->create();

            return "forms/{$form->external_id}/submissions";
        }],
        'export submissions (agent.leads.export)' => ['agent.leads.export', function (Agent $agent): string {
            $form = FormDefinition::factory()->forAgent($agent)->create();

            return "forms/{$form->external_id}/submissions/export";
        }],
        'list eval runs (agent.evals.view)' => ['agent.evals.view', fn (): string => 'evals/runs'],
        'show an eval run (agent.evals.view)' => ['agent.evals.view', function (Agent $agent): string {
            $run = EvalRun::create([
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'workspace_id' => $agent->workspace_id,
                'status' => EvalRunStatus::Completed,
                'total_cases' => 0,
                'started_at' => now(),
            ]);

            return "evals/runs/{$run->external_id}";
        }],
        'list topics (agent.topics.manage)' => ['agent.topics.manage', fn (): string => 'topics'],
        'analytics overview (agent.analytics.chats)' => ['agent.analytics.chats', fn (): string => 'analytics/overview'],
        'analytics sentiment (agent.analytics.sentiment)' => ['agent.analytics.sentiment', fn (): string => 'analytics/sentiment'],
        'analytics tone (agent.analytics.tone)' => ['agent.analytics.tone', fn (): string => 'analytics/tone'],
        'analytics topics (agent.analytics.topics)' => ['agent.analytics.topics', fn (): string => 'analytics/topics'],
        'analytics sources (agent.analytics.sources)' => ['agent.analytics.sources', fn (): string => 'analytics/sources'],
        'audit log (agent.audit.view)' => ['agent.audit.view', fn (): string => 'audit'],
    ]);
});

describe('workspace audit log (account.governance)', function (): void {

    it('lets the owner read it', function (): void {
        withApiToken($this->ownerToken)->getJson('/api/v1/audit')->assertOk();
    });

    it('forbids a member WITHOUT account.governance and lets one WITH it through', function (): void {
        AgentActivity::create([
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'workspace_id' => $this->agent->workspace_id,
            'trace_id' => (string) Str::uuid(),
            'tool_call_id' => 'call_'.Str::random(6),
            'tool_name' => 'get_weather',
            'tool_kind' => 'function',
            'outcome' => 'auto',
            'gated' => false,
            'is_error' => false,
            'arguments_truncated' => false,
            'result_truncated' => false,
        ]);

        withApiToken(permMemberToken($this->owner, []))->getJson('/api/v1/audit')->assertForbidden();

        withApiToken(permMemberToken($this->owner, ['account.governance']))
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });
});
