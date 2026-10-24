<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\AgentActivity;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Actions\Team\CreateTeamAction;
use Domain\Membership\Team\Data\CreateTeamData;
use Domain\Membership\Team\Models\Team;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Api\ActsAsApiOwner;

/**
 * The public API enforces the dashboard's permission model. Every route with
 * a permission gate is a row below, named by its route: a teammate holding
 * every permission but the one the route needs is refused and changes
 * nothing, and a teammate holding only that permission gets through (done,
 * or refused by validation for an empty body). An owner-only route refuses a
 * teammate holding every permission. The owner passes every gate, which the
 * resource tests show by running as the owner. Another workspace's records
 * are the Isolation sweep's.
 */
uses(ActsAsApiOwner::class);

beforeEach(function (): void {
    // Deleting a chatter wipes its photo on the assets disk and its files on
    // the private disk; deleting a source or an agent queues its clean-up.
    Storage::fake();
    Storage::fake('private');
    Storage::fake('assets');
    Bus::fake();
    $this->actAsApiOwner();
});

/**
 * Every permission a role can hold but $permission and the ones that need
 * it, so the one under test is the only one missing.
 *
 * @return list<string>
 */
function apiPermAllBut(?string $permission = null): array
{
    return collect(config('team-permissions.permissions'))
        ->filter(fn (array $definition, string $code): bool => Str::startsWith($code, ['agent.', 'account.'])
            && $code !== $permission
            && ! in_array($permission, $definition['requires'] ?? [], true))
        ->keys()
        ->all();
}

/**
 * A bearer token of a teammate of $owner whose role grants exactly
 * $permissions, on every agent unless $agentIds lists the ones it reaches.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentIds
 */
function apiPermToken(User $owner, array $permissions, bool $allAgents = true, array $agentIds = []): string
{
    return teammateWithPermissions($owner, $permissions, allAgents: $allAgents, agentExternalIds: $agentIds)
        ->createToken('account')->plainTextToken;
}

/**
 * Calls the named API route as the bearer of $token.
 *
 * @param  array<string, string>  $parameters
 * @param  array<string, mixed>  $body
 */
function apiPermCall(string $token, string $route, array $parameters = [], array $body = []): TestResponse
{
    $method = Route::getRoutes()->getByName($route)?->methods()[0] ?? throw new LogicException("No route {$route}.");

    return withApiToken($token)->json($method, route($route, $parameters), $body);
}

/** A second, empty team of the owner's workspace. */
function apiPermSpareTeam(User $owner): Team
{
    return app(CreateTeamAction::class)->execute(new CreateTeamData($owner->workspace(), 'Spare'));
}

/**
 * Requests gated by a permission that store nothing (reads, and minting a
 * link): [route, permission, the route's parameters (given the owner and the
 * agent), the status once through the gate, the refusal's status].
 *
 * @return array<string, array{0: string, 1: string, 2: Closure, 3?: int, 4?: int}>
 */
function apiPermReads(): array
{
    $agent = fn (Agent $agent, array $more = []): array => ['agent' => $agent->external_id, ...$more];

    return [
        'list functions' => ['api.v1.functions.index', 'account.functions', fn (): array => []],
        'show a function' => ['api.v1.functions.show', 'account.functions', fn (User $owner): array => ['function' => AgentFunction::factory()->forOwner($owner)->create()->external_id]],
        'list MCP servers' => ['api.v1.mcps.index', 'account.connectors', fn (): array => []],
        'show an MCP server' => ['api.v1.mcps.show', 'account.connectors', fn (User $owner): array => ['mcp' => AgentMcpServer::factory()->forOwner($owner)->create()->external_id]],
        'list chatters' => ['api.v1.chatters.index', 'account.chatters.manage', fn (): array => []],
        'show a chatter' => ['api.v1.chatters.show', 'account.chatters.manage', fn (User $owner): array => ['chatter' => Chatter::factory()->ofWorkspace($owner)->create()->external_id]],
        // Minting stores nothing; through the gate an empty body fails validation.
        'mint a sign-in link' => ['api.v1.chatters.sign', 'account.chatters.manage', fn (): array => [], 422],
        'read the workspace audit log' => ['api.v1.audit.index', 'account.governance', fn (): array => []],
        // The prompt library hides itself (404), like the dashboard's Library page.
        'list prompts' => ['api.v1.prompts.index', 'account.prompts', fn (): array => [], 200, 404],
        'list conversations' => ['api.v1.agents.conversations.index', 'agent.chat.view', fn (User $owner, Agent $a): array => $agent($a)],
        'show a conversation' => ['api.v1.agents.conversations.show', 'agent.chat.view', fn (User $owner, Agent $a): array => $agent($a, [
            'conversation' => Conversation::factory()->create(['agent_id' => $a->id, 'is_visible' => true])->external_id,
        ])],
        'list conversation messages' => ['api.v1.agents.conversations.messages.index', 'agent.chat.view', fn (User $owner, Agent $a): array => $agent($a, [
            'conversation' => Conversation::factory()->create(['agent_id' => $a->id, 'is_visible' => true])->external_id,
        ])],
        'list submissions' => ['api.v1.agents.forms.submissions.index', 'agent.leads.view', fn (User $owner, Agent $a): array => $agent($a, ['form' => FormDefinition::factory()->forAgent($a)->create()->external_id])],
        'export submissions' => ['api.v1.agents.forms.submissions.export', 'agent.leads.export', fn (User $owner, Agent $a): array => $agent($a, ['form' => FormDefinition::factory()->forAgent($a)->create()->external_id])],
        'list eval runs' => ['api.v1.agents.evals.runs.index', 'agent.evals.view', fn (User $owner, Agent $a): array => $agent($a)],
        'show an eval run' => ['api.v1.agents.evals.runs.show', 'agent.evals.view', fn (User $owner, Agent $a): array => $agent($a, ['run' => EvalRun::factory()->forAgent($a)->completed()->create()->external_id])],
        'list topics' => ['api.v1.agents.topics.index', 'agent.topics.manage', fn (User $owner, Agent $a): array => $agent($a)],
        'analytics overview' => ['api.v1.agents.analytics.overview', 'agent.analytics.chats', fn (User $owner, Agent $a): array => $agent($a)],
        'analytics sentiment' => ['api.v1.agents.analytics.sentiment', 'agent.analytics.sentiment', fn (User $owner, Agent $a): array => $agent($a)],
        'analytics tone' => ['api.v1.agents.analytics.tone', 'agent.analytics.tone', fn (User $owner, Agent $a): array => $agent($a)],
        'analytics topics' => ['api.v1.agents.analytics.topics', 'agent.analytics.topics', fn (User $owner, Agent $a): array => $agent($a)],
        'analytics sources' => ['api.v1.agents.analytics.sources', 'agent.analytics.sources', fn (User $owner, Agent $a): array => $agent($a)],
        'read the agent audit log' => ['api.v1.agents.audit.index', 'agent.audit.view', fn (User $owner, Agent $a): array => $agent($a)],
    ];
}

/**
 * Writes gated by a permission: [route, permission, the status once through
 * the gate, the request (given the owner and the agent) as its parameters,
 * its body and a check that the refused attempt changed nothing].
 *
 * @return array<string, array{0: string, 1: string, 2: int, 3: Closure}>
 */
function apiPermWrites(): array
{
    $agent = fn (Agent $agent, array $more = []): array => ['agent' => $agent->external_id, ...$more];

    return [
        'create a function' => ['api.v1.functions.store', 'account.functions', 422, fn (User $owner): array => [
            [], [], fn (): bool => $owner->workspace()->functions()->doesntExist(),
        ]],
        'update a function' => ['api.v1.functions.update', 'account.functions', 200, function (User $owner): array {
            $function = AgentFunction::factory()->forOwner($owner)->create(['description' => 'Original']);

            return [['function' => $function->external_id], ['description' => 'Renamed'], fn (): bool => $function->fresh()->description === 'Original'];
        }],
        'delete a function' => ['api.v1.functions.destroy', 'account.functions', 204, function (User $owner): array {
            $function = AgentFunction::factory()->forOwner($owner)->create();

            return [['function' => $function->external_id], [], fn (): bool => AgentFunction::whereKey($function->id)->exists()];
        }],
        'add an MCP server' => ['api.v1.mcps.store', 'account.connectors', 422, fn (User $owner): array => [
            [], [], fn (): bool => AgentMcpServer::query()->where('workspace_id', $owner->workspace()->id)->doesntExist(),
        ]],
        'delete an MCP server' => ['api.v1.mcps.destroy', 'account.connectors', 204, function (User $owner): array {
            $mcp = AgentMcpServer::factory()->forOwner($owner)->create();

            return [['mcp' => $mcp->external_id], [], fn (): bool => AgentMcpServer::whereKey($mcp->id)->exists()];
        }],
        'create a chatter' => ['api.v1.chatters.store', 'account.chatters.manage', 201, fn (User $owner, Agent $a): array => [
            [],
            ['name' => 'Jane', 'agent_ids' => [$a->external_id], 'email' => 'jane'.Str::random(6).'@example.test', 'password' => 'super-secret-pw'],
            fn (): bool => $owner->workspace()->chatters()->doesntExist(),
        ]],
        'update a chatter' => ['api.v1.chatters.update', 'account.chatters.manage', 200, function (User $owner): array {
            $chatter = Chatter::factory()->ofWorkspace($owner)->create();

            return [['chatter' => $chatter->external_id], ['deactivated' => true], fn (): bool => $chatter->fresh()->deactivated_at === null];
        }],
        'delete a chatter' => ['api.v1.chatters.destroy', 'account.chatters.manage', 204, function (User $owner, Agent $a): array {
            $chatter = Chatter::factory()->grantedTo($a)->create();

            return [['chatter' => $chatter->external_id], [], fn (): bool => Chatter::whereKey($chatter->id)->exists()];
        }],
        "grant a chatter's agents" => ['api.v1.chatters.agents.grant', 'account.chatters.manage', 204, function (User $owner, Agent $a): array {
            $chatter = Chatter::factory()->ofWorkspace($owner)->create();

            return [['chatter' => $chatter->external_id], ['agent_ids' => [$a->external_id]], fn (): bool => ! $chatter->canAccessAgent($a)];
        }],
        "revoke a chatter's agents" => ['api.v1.chatters.agents.revoke', 'account.chatters.manage', 204, function (User $owner, Agent $a): array {
            $chatter = Chatter::factory()->grantedTo($a)->create();

            return [['chatter' => $chatter->external_id], ['agent_ids' => [$a->external_id]], fn (): bool => $chatter->canAccessAgent($a)];
        }],
        'update the agent' => ['api.v1.agents.update', 'agent.setup', 200, fn (User $owner, Agent $a): array => [
            $agent($a), ['is_public' => true], fn (): bool => ! $a->fresh()->is_public,
        ]],
        'delete the agent' => ['api.v1.agents.destroy', 'agent.delete', 204, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => Agent::whereKey($a->id)->exists(),
        ]],
        'add a file' => ['api.v1.agents.sources.files.store', 'agent.sources.train.files', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => $a->files()->doesntExist(),
        ]],
        'delete a file' => ['api.v1.agents.sources.files.destroy', 'agent.sources.delete', 204, function (User $owner, Agent $a) use ($agent): array {
            $file = AgentFileFactory::new()->create(['workspace_id' => $a->workspace_id]);
            $a->files()->attach($file->id);

            return [$agent($a, ['file' => $file->external_id]), [], fn (): bool => $a->files()->whereKey($file->id)->exists()];
        }],
        'add a text' => ['api.v1.agents.sources.texts.store', 'agent.sources.train.text', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => $a->texts()->doesntExist(),
        ]],
        'delete a text' => ['api.v1.agents.sources.texts.destroy', 'agent.sources.delete', 204, function (User $owner, Agent $a) use ($agent): array {
            $text = SourceText::factory()->attachedTo($a)->create();

            return [$agent($a, ['text' => $text->external_id]), [], fn (): bool => $a->texts()->whereKey($text->id)->exists()];
        }],
        'add a web page' => ['api.v1.agents.sources.urls.store', 'agent.sources.train.web', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => $a->websites()->doesntExist(),
        ]],
        'delete a web page' => ['api.v1.agents.sources.urls.destroy', 'agent.sources.delete', 204, function (User $owner, Agent $a) use ($agent): array {
            $website = SourceWebsite::factory()->attachedTo($a)->ingested()->create();

            return [$agent($a, ['website' => $website->external_id]), [], fn (): bool => $a->websites()->whereKey($website->id)->exists()];
        }],
        'add a Q&A' => ['api.v1.agents.sources.qas.store', 'agent.sources.train.qa', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => AgentQuestionAnswer::query()->where('agent_id', $a->id)->doesntExist(),
        ]],
        'delete a Q&A' => ['api.v1.agents.sources.qas.destroy', 'agent.sources.delete', 204, function (User $owner, Agent $a) use ($agent): array {
            $qa = AgentQuestionAnswer::create(['agent_id' => $a->id, 'title' => 'Refunds', 'questions' => 'Can I get a refund?', 'answer' => 'Within 30 days, yes.']);

            return [$agent($a, ['qa' => $qa->external_id]), [], fn (): bool => AgentQuestionAnswer::query()->whereKey($qa->id)->exists()];
        }],
        'attach an MCP server' => ['api.v1.agents.mcps.attach', 'agent.sources.train.connectors', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => $a->mcpServers()->doesntExist(),
        ]],
        'detach an MCP server' => ['api.v1.agents.mcps.detach', 'agent.sources.train.connectors', 204, function (User $owner, Agent $a) use ($agent): array {
            $server = AgentMcpServer::factory()->attachedTo($a)->create();

            return [$agent($a, ['mcp' => $server->external_id]), [], fn (): bool => $a->mcpServers()->whereKey($server->id)->exists()];
        }],
        'attach a function' => ['api.v1.agents.functions.attach', 'agent.sources.train.functions', 200, function (User $owner, Agent $a) use ($agent): array {
            $function = AgentFunction::factory()->forOwner($owner)->create();

            return [$agent($a), ['function_id' => $function->external_id], fn (): bool => $a->functions()->doesntExist()];
        }],
        'detach a function' => ['api.v1.agents.functions.detach', 'agent.sources.train.functions', 204, function (User $owner, Agent $a) use ($agent): array {
            $function = AgentFunction::factory()->attachedTo($a)->create();

            return [$agent($a, ['function' => $function->external_id]), [], fn (): bool => $a->functions()->whereKey($function->id)->exists()];
        }],
        'delete a conversation' => ['api.v1.agents.conversations.destroy', 'agent.chat.delete', 204, function (User $owner, Agent $a) use ($agent): array {
            $conversation = Conversation::factory()->create(['agent_id' => $a->id]);

            return [$agent($a, ['conversation' => $conversation->external_id]), [], fn (): bool => Conversation::query()->whereKey($conversation->id)->exists()];
        }],
        'create a form' => ['api.v1.agents.forms.store', 'agent.sources.train.connectors', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => FormDefinition::query()->forAgent($a)->doesntExist(),
        ]],
        'update a form' => ['api.v1.agents.forms.update', 'agent.sources.train.connectors', 200, function (User $owner, Agent $a) use ($agent): array {
            $form = FormDefinition::factory()->forAgent($a)->create(['name' => 'Original']);

            return [$agent($a, ['form' => $form->external_id]), ['name' => 'Renamed'], fn (): bool => $form->fresh()->name === 'Original'];
        }],
        'delete a form' => ['api.v1.agents.forms.destroy', 'agent.sources.train.connectors', 204, function (User $owner, Agent $a) use ($agent): array {
            $form = FormDefinition::factory()->forAgent($a)->create();

            return [$agent($a, ['form' => $form->external_id]), [], fn (): bool => FormDefinition::query()->whereKey($form->id)->exists()];
        }],
        'start an eval run' => ['api.v1.agents.evals.runs.store', 'agent.evals.manage', 201, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => EvalRun::query()->forAgent($a->id)->doesntExist(),
        ]],
        'create a topic' => ['api.v1.agents.topics.store', 'agent.topics.manage', 422, fn (User $owner, Agent $a): array => [
            $agent($a), [], fn (): bool => Topic::query()->where('workspace_id', $a->workspace_id)->doesntExist(),
        ]],
        'rename a topic' => ['api.v1.agents.topics.update', 'agent.topics.manage', 422, function (User $owner, Agent $a) use ($agent): array {
            $topic = Topic::factory()->attachedTo($a)->create(['topic' => 'Shipping']);

            return [$agent($a, ['topic' => $topic->external_id]), [], fn (): bool => $topic->fresh()->topic === 'Shipping'];
        }],
        'detach a topic' => ['api.v1.agents.topics.destroy', 'agent.topics.manage', 204, function (User $owner, Agent $a) use ($agent): array {
            $topic = Topic::factory()->attachedTo($a)->create();

            return [$agent($a, ['topic' => $topic->external_id]), [], fn (): bool => $a->topics()->where('topics.id', $topic->id)->exists()];
        }],
    ];
}

/**
 * Owner-only reads, which no permission opens: [route, its parameters (given
 * the owner)].
 *
 * @return array<string, array{0: string, 1: Closure}>
 */
function apiPermOwnerReads(): array
{
    return [
        'list users' => ['api.v1.users.index', fn (): array => []],
        'list teams' => ['api.v1.teams.index', fn (): array => []],
        'show a team' => ['api.v1.teams.show', fn (User $owner): array => ['team' => ownerTeam($owner)->external_id]],
    ];
}

/**
 * Owner-only writes, which no permission opens: [route, the request (given
 * the owner and the agent) as its parameters, its body and a check that the
 * refused attempt changed nothing].
 *
 * @return array<string, array{0: string, 1: Closure}>
 */
function apiPermOwnerWrites(): array
{
    return [
        'create an agent' => ['api.v1.agents.store', fn (User $owner): array => [
            [], ['name' => 'Sneaky', 'team_id' => ownerTeam($owner)->external_id, 'is_public' => false], fn (): bool => Agent::query()->where('name', 'Sneaky')->doesntExist(),
        ]],
        'create a team' => ['api.v1.teams.store', fn (User $owner): array => [
            [], ['name' => 'Sneaky'], fn (): bool => $owner->workspace()->teams()->where('name', 'Sneaky')->doesntExist(),
        ]],
        'rename a team' => ['api.v1.teams.update', fn (User $owner): array => [
            ['team' => ownerTeam($owner)->external_id], ['name' => 'Hijacked'], fn (): bool => ownerTeam($owner)->name !== 'Hijacked',
        ]],
        'delete a team' => ['api.v1.teams.destroy', function (User $owner): array {
            $spare = apiPermSpareTeam($owner);

            return [['team' => $spare->external_id], [], fn (): bool => $spare->fresh() !== null];
        }],
        'remove a team member' => ['api.v1.teams.members.destroy', function (User $owner): array {
            $team = ownerTeam($owner);
            $member = User::factory()->employee($owner)->create();
            $team->members()->attach($member->id, ['role_id' => teamRole($team, 'Members', permissions: [])->id]);

            return [['team' => $team->external_id, 'member' => $member->external_id], [], fn (): bool => $team->members()->whereKey($member->id)->exists()];
        }],
        'create a role' => ['api.v1.teams.roles.store', fn (User $owner): array => [
            ['team' => ownerTeam($owner)->external_id], ['name' => 'Sneaky'], fn (): bool => ownerTeam($owner)->roles()->where('name', 'Sneaky')->doesntExist(),
        ]],
        'update a role' => ['api.v1.teams.roles.update', function (User $owner): array {
            $role = teamRole(ownerTeam($owner), 'Target', permissions: []);

            return [['team' => ownerTeam($owner)->external_id, 'role' => $role->external_id], ['name' => 'Hijacked'], fn (): bool => $role->fresh()->name === 'Target'];
        }],
        'delete a role' => ['api.v1.teams.roles.destroy', function (User $owner): array {
            $role = teamRole(ownerTeam($owner), 'Target', permissions: []);

            return [['team' => ownerTeam($owner)->external_id, 'role' => $role->external_id], [], fn (): bool => $role->fresh() !== null];
        }],
        'assign a role' => ['api.v1.teams.roles.users.store', function (User $owner): array {
            $team = ownerTeam($owner);
            $role = teamRole($team, 'Target', permissions: []);
            $teammate = User::factory()->employee($owner)->create();

            return [['team' => $team->external_id, 'role' => $role->external_id], ['user_id' => $teammate->external_id], fn (): bool => $team->members()->whereKey($teammate->id)->doesntExist()];
        }],
    ];
}

describe('routes gated by a permission', function (): void {
    it('refuses a read to a teammate without its permission and lets one holding only it through', function (string $route, string $permission, Closure $parameters, int $allowed = 200, int $denied = 403): void {
        $parameters = $parameters($this->owner, $this->agent);

        apiPermCall(apiPermToken($this->owner, apiPermAllBut($permission)), $route, $parameters)->assertStatus($denied);

        apiPermCall(apiPermToken($this->owner, [$permission]), $route, $parameters)->assertStatus($allowed);
    })->with(apiPermReads());

    it('refuses a write to a teammate without its permission, changing nothing, and lets one holding only it through', function (string $route, string $permission, int $allowed, Closure $request): void {
        [$parameters, $body, $untouched] = $request($this->owner, $this->agent);

        apiPermCall(apiPermToken($this->owner, apiPermAllBut($permission)), $route, $parameters, $body)->assertForbidden();

        expect($untouched())->toBeTrue();

        apiPermCall(apiPermToken($this->owner, [$permission]), $route, $parameters, $body)->assertStatus($allowed);
    })->with(apiPermWrites());
});

describe('owner-only routes', function (): void {
    it('refuses an owner-only read to a teammate holding every permission', function (string $route, Closure $parameters): void {
        apiPermCall(apiPermToken($this->owner, apiPermAllBut()), $route, $parameters($this->owner))->assertForbidden();
    })->with(apiPermOwnerReads());

    it('refuses an owner-only write to a teammate holding every permission and changes nothing', function (string $route, Closure $request): void {
        [$parameters, $body, $untouched] = $request($this->owner, $this->agent);

        apiPermCall(apiPermToken($this->owner, apiPermAllBut()), $route, $parameters, $body)->assertForbidden();

        expect($untouched())->toBeTrue();
    })->with(apiPermOwnerWrites());
});

it('has a row for every API route with a permission gate', function (): void {
    $gated = collect(Route::getRoutes()->getRoutesByName())
        ->filter(fn (RouteDefinition $route, string $name): bool => Str::startsWith($name, 'api.v1.')
            && collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => Str::startsWith($middleware, ['team.permission:', 'agent.permission:', 'api.owner'])))
        ->keys()
        ->sort()
        ->values()
        ->all();

    $rows = collect([...apiPermReads(), ...apiPermWrites(), ...apiPermOwnerReads(), ...apiPermOwnerWrites()])
        ->map(fn (array $row): string => $row[0])
        ->sort()
        ->values()
        ->all();

    expect($rows)->toBe($gated);
});

describe('agent access', function (): void {
    it('lets a teammate with no permission read the agent and open a chat with it', function (string $route, Closure $parameters, int $status): void {
        apiPermCall(apiPermToken($this->owner, []), $route, $parameters($this->agent))->assertStatus($status);
    })->with([
        'show the agent' => ['api.v1.agents.show', fn (Agent $agent): array => ['agent' => $agent->external_id], 200],
        'list its sources' => ['api.v1.agents.sources.index', fn (Agent $agent): array => ['agent' => $agent->external_id], 200],
        'list its MCP servers' => ['api.v1.agents.mcps.index', fn (Agent $agent): array => ['agent' => $agent->external_id], 200],
        'list its functions' => ['api.v1.agents.functions.index', fn (Agent $agent): array => ['agent' => $agent->external_id], 200],
        'list its forms' => ['api.v1.agents.forms.index', fn (Agent $agent): array => ['agent' => $agent->external_id], 200],
        'show a form' => ['api.v1.agents.forms.show', fn (Agent $agent): array => ['agent' => $agent->external_id, 'form' => FormDefinition::factory()->forAgent($agent)->create()->external_id], 200],
        'open a conversation' => ['api.v1.agents.conversations.store', fn (Agent $agent): array => ['agent' => $agent->external_id], 201],
    ]);

    it('forbids a teammate an agent their role does not reach, whatever their permissions', function (): void {
        $ungranted = createAgent($this->owner, ['name' => 'Other']);
        $token = apiPermToken($this->owner, ['agent.chat.view'], allAgents: false, agentIds: [$this->agent->external_id]);

        apiPermCall($token, 'api.v1.agents.conversations.index', ['agent' => $this->agent->external_id])->assertOk();
        apiPermCall($token, 'api.v1.agents.conversations.index', ['agent' => $ungranted->external_id])->assertForbidden();
        apiPermCall($token, 'api.v1.agents.show', ['agent' => $ungranted->external_id])->assertForbidden();
    });

    it("404s a record of another agent of the same workspace named under this agent's id", function (string $route, Closure $parameters, array $body): void {
        $other = createAgent($this->owner);

        apiPermCall($this->token, $route, ['agent' => $this->agent->external_id, ...$parameters($other)], $body)->assertNotFound();
    })->with([
        "the submissions of another agent's form" => ['api.v1.agents.forms.submissions.index', fn (Agent $other): array => ['form' => FormDefinition::factory()->forAgent($other)->create()->external_id], []],
        "another agent's Q&A" => ['api.v1.agents.sources.qas.destroy', fn (Agent $other): array => [
            'qa' => AgentQuestionAnswer::create(['agent_id' => $other->id, 'title' => 'T', 'questions' => 'Q?', 'answer' => 'A long enough answer.'])->external_id,
        ], []],
        "a message to another agent's conversation" => ['api.v1.agents.conversations.messages.store', fn (Agent $other): array => [
            'conversation' => Conversation::factory()->create(['agent_id' => $other->id])->external_id,
        ], ['message' => 'Hi']],
    ]);
});

describe("a teammate acts on the owner's workspace", function (): void {
    it('keys a chatter a teammate creates to the workspace', function (): void {
        $id = apiPermCall(apiPermToken($this->owner, ['account.chatters.manage']), 'api.v1.chatters.store', [], [
            'name' => 'Jane', 'agent_ids' => [$this->agent->external_id], 'email' => 'jane@example.test', 'password' => 'super-secret-pw',
        ])->assertCreated()->json('data.id');

        expect(Chatter::where('external_id', $id)->value('workspace_id'))->toBe($this->owner->workspace()->id);
    });

    it("shows a teammate holding account.governance the workspace's audit rows", function (): void {
        AgentActivity::factory()->forAgent($this->agent)->create();

        apiPermCall(apiPermToken($this->owner, ['account.governance']), 'api.v1.audit.index')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });
});
