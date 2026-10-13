<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Evaluation\Enums\EvalRunStatus;
use Domain\Evaluation\Models\EvalCase;
use Domain\Evaluation\Models\EvalRun;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Two workspaces, A and B
|--------------------------------------------------------------------------
|
| B's owner (in the dashboard, or with B's API key) must never reach A's
| data by naming it in a URL:
|
|  - every dashboard and API route that takes an agent answers 404 for A's
|    agent, as if it did not exist;
|  - a route that names B's own agent plus a record of A (a conversation, a
|    source, a tool, a chatter…) answers 404 too: the record is looked up
|    inside the agent or the workspace, never by its id alone.
|
| The sweep walks the route table, so a new route is covered the day it is
| added. Nothing of A may change.
*/

beforeEach(function (): void {
    Storage::fake();
    Storage::fake('public');
    $this->withoutMiddleware(ThrottleRequests::class);

    $this->ownerA = User::factory()->create();
    $this->agentA = createAgent($this->ownerA, ['name' => 'Agent A']);

    $this->ownerB = User::factory()->create();
    $this->agentB = createAgent($this->ownerB, ['name' => 'Agent B']);
});

/**
 * Signed-in dashboard or API routes that take an `{agent}`.
 *
 * @return list<Route>
 */
function isolationAgentRoutes(bool $api): array
{
    return array_values(array_filter(
        isolationSignedInRoutes($api),
        fn (Route $route): bool => in_array('agent', $route->parameterNames(), true),
    ));
}

/**
 * Signed-in dashboard or API routes that take a workspace record but no agent.
 *
 * @return list<Route>
 */
function isolationWorkspaceRoutes(bool $api): array
{
    return array_values(array_filter(
        isolationSignedInRoutes($api),
        fn (Route $route): bool => $route->parameterNames() !== [] && ! in_array('agent', $route->parameterNames(), true),
    ));
}

/**
 * @return list<Route>
 */
function isolationSignedInRoutes(bool $api): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => $route->getName() !== null
            && str_starts_with($route->uri(), 'api/') === $api
            && ! str_starts_with($route->uri(), 'chat/')
            && collect($route->gatherMiddleware())->contains(
                fn (mixed $middleware): bool => is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:')),
            ))
        ->values()
        ->all();
}

function isolationMethod(Route $route): string
{
    return collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first();
}

/**
 * A record of workspace A for a route parameter, attached to A's agent where
 * that applies, or null when the parameter is not a tenant record (a settings
 * group, a connector slug…). The URL builder picks the key the route expects.
 */
function isolationRecordOf(Route $route, string $parameter, Agent $agent): ?Model
{
    return match ($parameter) {
        'conversation' => isolationConversation($agent),
        'conversationId' => isolationConversation($agent),
        'function', 'external_id' => isolationFunction($agent),
        'mcp' => isolationMcpServer($agent),
        'file', 'source' => isolationFile($agent),
        'fileId' => isolationFile($agent),
        'website' => isolationWebsite($agent),
        'websiteId' => isolationWebsite($agent),
        'textId' => isolationText($agent),
        'id' => str_contains($route->uri(), '/sources/qa/') ? isolationQuestionAnswer($agent) : isolationText($agent),
        'topic' => isolationTopic($agent),
        'chatter' => Chatter::factory()->grantedTo($agent)->create(),
        'form' => FormDefinition::factory()->forAgent($agent)->create(),
        'run' => EvalRun::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->workspace_id,
            'status' => EvalRunStatus::Completed,
            'total_cases' => 0,
            'started_at' => now(),
        ]),
        'case' => EvalCase::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->workspace_id,
            'name' => 'Refunds',
            'input' => 'How long do refunds take?',
            'scorer_type' => 'contains',
            'scorer_config' => ['value' => '5 days'],
            'active' => true,
        ]),
        default => null,
    };
}

/**
 * A record of workspace A for a workspace-level route parameter, or null when
 * the parameter is not one of A's records (a connector slug, a provider…).
 */
function isolationWorkspaceRecordOf(Route $route, string $parameter, User $owner, Agent $agent): ?Model
{
    return match ($parameter) {
        'chatter' => Chatter::factory()->grantedTo($agent)->create(),
        'function' => isolationFunction($agent),
        'external_id' => str_contains($route->uri(), '/prompts/')
            ? AgentPrompt::factory()->create(['workspace_id' => $agent->workspace_id])
            : isolationFunction($agent),
        'mcp' => isolationMcpServer($agent),
        'team' => $owner->workspace()->teams()->oldest('id')->first(),
        'member' => User::factory()->employee($owner)->create(),
        'sourceFile' => isolationFile($agent),
        'export' => DataExport::query()->create(['user_id' => $owner->id, 'status' => DataExportStatus::Ready, 'download_token' => Str::random(40), 'expires_at' => now()->addDay()]),
        'tokenId' => $owner->createToken('account')->accessToken,
        default => null,
    };
}

function isolationConversation(Agent $agent): Conversation
{
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    return Conversation::factory()->create(['chat_session_id' => $session->id, 'agent_id' => $agent->id, 'is_visible' => true]);
}

function isolationFunction(Agent $agent): AgentFunction
{
    $function = AgentFunction::query()->create([
        'workspace_id' => $agent->workspace_id,
        'internal_name' => 'lookup_'.Str::lower(Str::random(6)),
        'description' => 'Looks up an order',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/hook',
        'webhook_method' => 'POST',
    ]);
    $agent->functions()->attach($function->id);

    return $function;
}

function isolationMcpServer(Agent $agent): AgentMcpServer
{
    $server = AgentMcpServer::query()->create([
        'workspace_id' => $agent->workspace_id,
        'name' => 'Private tools',
        'slug' => 'private-'.Str::lower(Str::random(6)),
        'url' => 'https://mcp.example.test/private',
        'auth_type' => 'none',
        'is_enabled' => true,
    ]);
    $agent->mcpServers()->attach($server->id);

    return $server;
}

function isolationFile(Agent $agent): Model
{
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);

    return $file;
}

function isolationWebsite(Agent $agent): SourceWebsite
{
    $website = SourceWebsite::query()->create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://docs.example.test/'.Str::lower(Str::random(6)),
        'status' => 'ingested',
    ]);
    $agent->websites()->attach($website->id);

    return $website;
}

function isolationText(Agent $agent): SourceText
{
    $text = SourceText::query()->create([
        'workspace_id' => $agent->workspace_id,
        'filepath' => $agent->storageRoot().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'Our refund policy',
        'length' => 17,
        'checksum' => md5(Str::uuid()->toString()),
    ]);
    $agent->texts()->attach($text->id);

    return $text;
}

function isolationQuestionAnswer(Agent $agent): AgentQuestionAnswer
{
    return AgentQuestionAnswer::query()->create([
        'agent_id' => $agent->id,
        'title' => 'Refunds',
        'answer' => 'Refunds take 5 business days.',
        'questions' => 'How long do refunds take?',
    ]);
}

function isolationTopic(Agent $agent): Topic
{
    $topic = Topic::query()->create([
        'workspace_id' => $agent->workspace_id,
        'topic' => 'Secret plans '.Str::random(6),
        'description' => 'Only for workspace A',
    ]);
    $agent->topics()->attach($topic->id);

    return $topic;
}

/**
 * A model bound by the route keeps its own route key; a parameter the
 * controller looks up itself gets the public `external_id` it expects.
 *
 * @param  array<string, mixed>  $values
 * @return array<string, mixed>
 */
function isolationUrlValues(Route $route, array $values): array
{
    $types = collect($route->signatureParameters())
        ->mapWithKeys(fn (ReflectionParameter $parameter): array => [$parameter->getName() => (string) $parameter->getType()]);

    foreach ($values as $name => $value) {
        $type = $types->get($name, 'string');

        if ($value instanceof Model && ! is_subclass_of($type, Model::class)) {
            $values[$name] = $type === 'int' ? $value->getKey() : ($value->getAttribute('external_id') ?? $value->getKey());
        }
    }

    return $values;
}

/**
 * Sends each route once and lists every answer that is not a 404.
 *
 * @param  list<Route>  $routes
 * @param  Closure(Route): (array<string, mixed>|null)  $parameters  null skips the route
 * @param  array<string, string>  $headers
 * @return list<string>
 */
function isolationLeaks(object $test, array $routes, Closure $parameters, array $headers = []): array
{
    // Every record is made before the first request: a request that fails in
    // the database would otherwise break the inserts that follow it.
    $requests = [];

    foreach ($routes as $route) {
        $values = $parameters($route);

        if ($values !== null) {
            $requests[] = [isolationMethod($route), $route->uri(), route($route->getName(), isolationUrlValues($route, $values))];
        }
    }

    $leaks = [];

    foreach ($requests as [$method, $uri, $url]) {
        $response = $test->withHeaders($headers)->json($method, $url);
        $status = $response->status();

        // A 422 for the empty body comes from validation, before any lookup,
        // so it says nothing about the record.
        if ($status !== 404 && $status !== 422) {
            $error = $status >= 500 && $response->exception !== null
                ? ' ('.class_basename($response->exception).': '.Str::limit($response->exception->getMessage(), 300).')'
                : '';
            $leaks[] = "{$method} {$uri} → {$status}{$error}";
        }
    }

    return $leaks;
}

it("answers 404 on every dashboard route for another workspace's agent", function (): void {
    $this->actingAs($this->ownerB);

    $leaks = isolationLeaks($this, isolationAgentRoutes(api: false), fn (Route $route): ?array => $route->parameterNames() === ['agent']
        ? ['agent' => $this->agentA]
        : null);

    expect($leaks)->toBe([])
        ->and($this->agentA->fresh()?->name)->toBe('Agent A');
});

it("answers 404 on every API route for another workspace's agent", function (): void {
    $headers = ['Authorization' => 'Bearer '.$this->ownerB->createToken('account')->plainTextToken];

    $leaks = isolationLeaks($this, isolationAgentRoutes(api: true), fn (Route $route): ?array => $route->parameterNames() === ['agent']
        ? ['agent' => $this->agentA]
        : null, $headers);

    expect($leaks)->toBe([])
        ->and($this->agentA->fresh()?->name)->toBe('Agent A');
});

it("answers 404 when a dashboard route pairs the caller's agent with another workspace's record", function (): void {
    $this->actingAs($this->ownerB);

    $leaks = isolationLeaks($this, isolationAgentRoutes(api: false), function (Route $route): ?array {
        $others = array_values(array_diff($route->parameterNames(), ['agent']));

        if (count($others) !== 1 || ! ($record = isolationRecordOf($route, $others[0], $this->agentA)) instanceof Model) {
            return null;
        }

        return ['agent' => $this->agentB, $others[0] => $record];
    });

    expect($leaks)->toBe([]);
});

it("answers 404 when an API route pairs the caller's agent with another workspace's record", function (): void {
    $headers = ['Authorization' => 'Bearer '.$this->ownerB->createToken('account')->plainTextToken];

    $leaks = isolationLeaks($this, isolationAgentRoutes(api: true), function (Route $route): ?array {
        $others = array_values(array_diff($route->parameterNames(), ['agent']));

        if (count($others) !== 1 || ! ($record = isolationRecordOf($route, $others[0], $this->agentA)) instanceof Model) {
            return null;
        }

        return ['agent' => $this->agentB, $others[0] => $record];
    }, $headers);

    expect($leaks)->toBe([]);
});

it("answers 404 on every dashboard route for another workspace's record", function (): void {
    $this->actingAs($this->ownerB);

    $leaks = isolationLeaks($this, isolationWorkspaceRoutes(api: false), function (Route $route): ?array {
        $parameters = $route->parameterNames();

        if (count($parameters) !== 1 || ! ($record = isolationWorkspaceRecordOf($route, $parameters[0], $this->ownerA, $this->agentA)) instanceof Model) {
            return null;
        }

        return [$parameters[0] => $record];
    });

    expect($leaks)->toBe([]);
});

it("answers 404 on every API route for another workspace's record", function (): void {
    $headers = ['Authorization' => 'Bearer '.$this->ownerB->createToken('account')->plainTextToken];

    $leaks = isolationLeaks($this, isolationWorkspaceRoutes(api: true), function (Route $route): ?array {
        $parameters = $route->parameterNames();

        if (count($parameters) !== 1 || ! ($record = isolationWorkspaceRecordOf($route, $parameters[0], $this->ownerA, $this->agentA)) instanceof Model) {
            return null;
        }

        return [$parameters[0] => $record];
    }, $headers);

    expect($leaks)->toBe([]);
});

it("answers 404 when a route pairs the caller's team with another workspace's member or role", function (): void {
    $this->actingAs($this->ownerB);
    $teamB = $this->ownerB->workspace()->teams()->oldest('id')->firstOrFail();
    $memberA = User::factory()->employee($this->ownerA)->create();
    $teamA = $this->ownerA->workspace()->teams()->oldest('id')->firstOrFail();
    $teamA->members()->attach($memberA->id, ['role_id' => $teamA->roles()->value('id')]);

    expect($this->deleteJson(route('teams.remove-member', ['team' => $teamB, 'member' => $memberA]))->status())->toBe(404)
        ->and($this->putJson(route('teams.update-member', ['team' => $teamB, 'member' => $memberA]), ['role_id' => $teamB->roles()->value('id')])->status())->toBe(404)
        ->and($teamA->members()->whereKey($memberA->id)->exists())->toBeTrue();
});
