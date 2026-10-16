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
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Membership\Team\Models\Team;
use Domain\Notification\Models\UserNotification;
use Domain\Security\Models\BlockedVisitor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
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
| data by naming it in a URL. Every signed-in dashboard and API route that
| names a record (an agent, a conversation, a source, a tool, a team, a
| role…) answers 404 when any one of those records is A's and the rest are
| B's own: each record is looked up inside the caller's workspace (or inside
| the record before it), never by its id alone.
|
| The sweep walks the route table. Every route parameter must be either a
| record kind the sweep can build or a plain value listed in
| isolationPlainValue(), so a route with a new kind of record fails here
| until it is swept too. Nothing of A may change.
*/

beforeEach(function (): void {
    Storage::fake();
    $this->withoutMiddleware(ThrottleRequests::class);
    // Swept like every other source, so their routes must be on.
    config(['chatvia.features.youtube_sources' => true]);

    $this->ownerA = User::factory()->create();
    $this->agentA = createAgent($this->ownerA, ['name' => 'Agent A']);

    $this->ownerB = User::factory()->create();
    $this->agentB = createAgent($this->ownerB, ['name' => 'Agent B']);
});

/**
 * Signed-in dashboard or API routes. A signed link is left out: its
 * signature, not a lookup, names the record (each has its own test).
 *
 * @return list<Route>
 */
function isolationSignedInRoutes(bool $api): array
{
    return collect(Router::getRoutes()->getRoutes())
        ->filter(function (Route $route) use ($api): bool {
            $middleware = collect($route->gatherMiddleware())->filter(fn (mixed $middleware): bool => is_string($middleware));

            return $route->getName() !== null
                && str_starts_with($route->uri(), 'api/') === $api
                && ! str_starts_with($route->uri(), 'chat/')
                && $middleware->contains(fn (string $middleware): bool => $middleware === 'auth' || str_starts_with($middleware, 'auth:'))
                && ! $middleware->contains(fn (string $middleware): bool => $middleware === 'signed' || str_starts_with($middleware, 'signed:') || str_starts_with($middleware, ValidateSignature::class));
        })
        ->values()
        ->all();
}

function isolationMethod(Route $route): string
{
    return collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first();
}

/**
 * The value of a route parameter that is not a workspace record, so it cannot
 * name another workspace's data; null for a parameter that is a record (or
 * is not known yet).
 */
function isolationPlainValue(string $parameter): ?string
{
    return match ($parameter) {
        // A connector or capability slug, looked up inside the caller's workspace.
        'slug' => 'notion',
        // A connector tool's name, inside that connector.
        'tool' => 'search',
        // An agent settings group.
        'group' => 'embed',
        // A social sign-in provider.
        'provider' => 'google',
        // The kind of knowledge source in a file-viewer URL (next to {externalId}).
        'type' => 'file',
        // The public address of a hosted chat.
        'agentSlug' => 'acme-support',
        // Laravel Passkeys' own route, which looks the passkey up among the
        // signed-in user's (and answers 403 for anyone else's).
        'passkey' => '1',
        default => null,
    };
}

/**
 * A record of the owner's workspace for a route parameter, attached to the
 * owner's agent where that applies, or null when the parameter is not a
 * record. $built holds the records already made for the same URL, so a record
 * can belong to the one before it (a link to its website, a role to its team).
 * The URL builder picks the key the route expects.
 *
 * @param  array<string, mixed>  $built
 */
function isolationRecordOf(Route $route, string $parameter, User $owner, Agent $agent, array $built): ?Model
{
    $team = fn (): Team => ($built['team'] ?? null) instanceof Team ? $built['team'] : $owner->workspace()->teams()->oldest('id')->firstOrFail();

    return match ($parameter) {
        'agent' => $agent,
        'conversation', 'conversationId' => isolationConversation($agent),
        'function' => isolationFunction($agent),
        'external_id' => str_contains($route->uri(), '/prompts/')
            ? AgentPrompt::factory()->create(['workspace_id' => $agent->workspace_id])
            : isolationFunction($agent),
        'mcp' => isolationMcpServer($agent),
        'file', 'source', 'fileId', 'sourceFile', 'externalId' => isolationFile($agent),
        'website', 'websiteId' => isolationWebsite($agent),
        'link' => isolationLink($built['website'] ?? isolationWebsite($agent)),
        'text', 'textId' => isolationText($agent),
        'id' => str_contains($route->uri(), '/sources/qa/') ? isolationQuestionAnswer($agent) : isolationText($agent),
        'youtube' => isolationYoutubeVideo($agent),
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
        'team' => $team(),
        'role', 'roleId' => $team()->roles()->oldest('id')->firstOrFail(),
        'invitationId' => $team()->invitations()->create([
            'email' => Str::lower(Str::random(8)).'@example.test',
            'role_id' => $team()->roles()->oldest('id')->value('id'),
        ]),
        'member' => User::factory()->employee($owner)->create(),
        'export' => DataExport::query()->create(['user_id' => $owner->id, 'status' => DataExportStatus::Ready, 'download_token' => Str::random(40), 'expires_at' => now()->addDay()]),
        'tokenId' => $owner->createToken('account')->accessToken,
        'event' => GuardrailEvent::create([
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'workspace_id' => $agent->workspace_id,
            'visitor_key' => hash('sha256', 'isolation-visitor'),
            'stage' => 'input',
            'guard_type' => 'prompt_injection',
            'outcome' => 'blocked',
        ]),
        'blockedVisitor' => BlockedVisitor::factory()->create(['workspace_id' => $agent->workspace_id]),
        // One person's own notification: anyone else, even in the same workspace, gets a 404.
        'notification' => UserNotification::factory()->create(['user_id' => $owner->id]),
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
    return AgentFunction::factory()->attachedTo($agent)->create(['description' => 'Looks up an order']);
}

function isolationMcpServer(Agent $agent): AgentMcpServer
{
    $server = AgentMcpServer::factory()->create([
        'workspace_id' => $agent->workspace_id,
        'name' => 'Private tools',
        'slug' => 'private-'.Str::lower(Str::random(6)),
    ]);
    $agent->mcpServers()->attach($server->id);

    return $server;
}

/** A knowledge file with its stored content, so a download route would really serve it. */
function isolationFile(Agent $agent): Model
{
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);
    $agent->files()->attach($file->id);
    Storage::put($file->filepath, 'Secret plans of workspace A');

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

function isolationLink(SourceWebsite $website): AgentWebsiteUrl
{
    return $website->urls()->create(['url' => $website->url.'/page-'.Str::lower(Str::random(4)), 'status' => SourceStatus::Completed]);
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

function isolationYoutubeVideo(Agent $agent): SourceYoutubeVideo
{
    $video = SourceYoutubeVideo::query()->create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://www.youtube.com/watch?v='.Str::random(11),
        'length' => 0,
        'status' => SourceStatus::Pending,
    ]);
    $agent->youtubeVideos()->attach($video->id);

    return $video;
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
            $values[$name] = $type === 'int' || ! array_key_exists('external_id', $value->getAttributes())
                ? $value->getKey()
                : $value->getAttribute('external_id');
        }
    }

    return $values;
}

/**
 * A valid body of the caller's own for a route that validates its input
 * before it looks the record up, so the sweep reaches the lookup. Every other
 * route gets an empty body.
 *
 * @param  array<string, mixed>  $ofB  the route's values of workspace B
 * @return array<string, mixed>
 */
function isolationBody(Route $route, array $ofB): array
{
    return match ($route->getName()) {
        'people.chatters.update' => ['deactivated' => false],
        'teams.update-role' => ['name' => 'Renamed', 'all_agents' => true],
        'agents.sources.text.update' => ['content' => str_repeat('Our new refund policy. ', 25)],
        'agents.sources.qa.update' => ['title' => 'Refunds', 'questions' => [['question' => 'How long?']], 'answer' => 'Five days.'],
        'agents.sources.files.link' => ['file_id' => $ofB['fileId']->getKey()],
        'agents.sources.texts.link' => ['text_id' => $ofB['textId']->getKey()],
        'agents.sources.trained-websites.link' => ['website_id' => $ofB['websiteId']->getKey()],
        default => [],
    };
}

/**
 * Every request of the sweep: for each record a route names, one request in
 * which that record is workspace A's and every other record is B's own.
 * Routes that name no record are left out; a parameter that is neither a
 * record nor a plain value is listed in $unknown instead.
 *
 * @param  list<Route>  $routes
 * @param  list<string>  $unknown
 * @return list<array{0: string, 1: string, 2: string, 3: array<string, mixed>}>
 */
function isolationRequests(object $test, array $routes, array &$unknown): array
{
    $requests = [];

    foreach ($routes as $route) {
        $parameters = $route->parameterNames();
        $missing = array_values(array_filter(
            $parameters,
            fn (string $parameter): bool => isolationPlainValue($parameter) === null && ! in_array($parameter, isolationRecordParameters(), true),
        ));

        if ($missing !== []) {
            array_push($unknown, ...array_map(fn (string $parameter): string => "{$route->getName()} {{$parameter}}", $missing));

            continue;
        }

        $records = array_values(array_filter($parameters, fn (string $parameter): bool => isolationPlainValue($parameter) === null));

        foreach ($records as $foreign) {
            // Every record is made before the first request: a request that
            // fails in the database would otherwise break the inserts after it.
            $ofA = [];
            $ofB = [];

            foreach ($parameters as $parameter) {
                $plain = isolationPlainValue($parameter);
                $ofA[$parameter] = $plain ?? isolationRecordOf($route, $parameter, $test->ownerA, $test->agentA, $ofA);
                $ofB[$parameter] = $plain ?? isolationRecordOf($route, $parameter, $test->ownerB, $test->agentB, $ofB);
            }

            $values = [...$ofB, $foreign => $ofA[$foreign]];
            $requests[] = [isolationMethod($route), "{$route->uri()} (A's {{$foreign}})", route($route->getName(), isolationUrlValues($route, $values)), isolationBody($route, $ofB)];
        }
    }

    return $requests;
}

/**
 * The record parameters isolationRecordOf() can build.
 *
 * @return list<string>
 */
function isolationRecordParameters(): array
{
    return [
        'agent', 'conversation', 'conversationId', 'function', 'external_id', 'mcp', 'file', 'source', 'fileId',
        'sourceFile', 'externalId', 'website', 'websiteId', 'link', 'text', 'textId', 'id', 'youtube', 'topic',
        'chatter', 'form', 'run', 'case', 'team', 'role', 'roleId', 'invitationId', 'member', 'export', 'tokenId',
        'event', 'blockedVisitor', 'notification',
    ];
}

/**
 * Answers the sweep found that are not a 404, each a known bug: shrink-only,
 * so a new leak fails and a fixed one must be removed from here.
 *
 * @return list<string>
 */
function isolationKnownLeaks(): array
{
    return [];
}

/**
 * Sends each request once and lists every answer that is not a 404.
 *
 * @param  list<array{0: string, 1: string, 2: string, 3: array<string, mixed>}>  $requests
 * @param  array<string, string>  $headers
 * @return list<string>
 */
function isolationLeaks(object $test, array $requests, array $headers = []): array
{
    $leaks = [];

    foreach ($requests as [$method, $label, $url, $body]) {
        $response = $test->withHeaders($headers)->json($method, $url, $body);
        $status = $response->status();

        if ($status !== 404) {
            $error = $status >= 500 && $response->exception !== null
                ? ' ('.class_basename($response->exception).': '.Str::limit($response->exception->getMessage(), 300).')'
                : '';
            $invalid = $status === 422 ? ' '.json_encode($response->json('errors')) : '';
            $leaks[] = "{$method} {$label} → {$status}{$error}{$invalid}";
        }
    }

    return $leaks;
}

it("answers 404 when any record a route names is another workspace's", function (bool $api, int $floor): void {
    $headers = [];

    if ($api) {
        $headers = ['Authorization' => 'Bearer '.$this->ownerB->createToken('account')->plainTextToken];
    } else {
        $this->actingAs($this->ownerB);
    }

    $unknown = [];
    $requests = isolationRequests($this, isolationSignedInRoutes($api), $unknown);
    $leaks = isolationLeaks($this, $requests, $headers);
    $known = $api ? [] : isolationKnownLeaks();

    expect($unknown)->toBe([])
        ->and(count($requests))->toBeGreaterThanOrEqual($floor)
        ->and(array_values(array_diff($leaks, $known)))->toBe([])
        ->and(array_values(array_diff($known, $leaks)))->toBe([])
        ->and($this->agentA->fresh()?->name)->toBe('Agent A');
})->with([
    // The floors sit a little under today's counts: a sweep that suddenly
    // sends far fewer requests has lost its routes (a middleware renamed…).
    'dashboard' => [false, 240],
    'API' => [true, 36],
]);

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
