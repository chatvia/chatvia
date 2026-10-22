<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ReadsClassAttributes;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\ExecutableFinder;
use Tests\RefreshTestDatabases;
use Tests\Support\FakeLlmProviders;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    ->in('Feature', 'Unit');

// Eval harnesses (e.g. tool-routing retrieval quality) need the full app + a
// transactional DB like Feature/Unit, but run the REAL embeddings provider and
// skip themselves without an API key — so they stay out of normal CI on their own.
pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    ->in('Eval');

// Architecture tests are framework-free guardrails (e.g. "every Action has a
// test"). They don't need a TestCase or DB; binding them to the bare PHPUnit
// TestCase keeps them fast and avoids RefreshTestDatabases overhead.
pest()->in('Architecture');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Shared set-up for the Unit and Feature tests. Use these instead of a local
| copy in a test file.
|
*/

/**
 * The owner's first team, created when the workspace has none yet. Read
 * from the database, not through primaryTeam(), which the User remembers:
 * after createAgent() made the team, a later helper call on the same User
 * would see the remembered "no team" and make a second one.
 */
function ownerTeam(User $owner): Team
{
    return $owner->ownedTeams()->oldest('id')->first() ?? app(TeamService::class)->createDefaultTeam($owner);
}

/**
 * Create a minimal agent in the user's workspace for unit tests, ensuring the
 * user has a primary team. Pass any column override via `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 */
function createAgent(User $user, array $overrides = []): Agent
{
    $team = ownerTeam($user);

    return Agent::create(array_merge([
        'workspace_id' => $user->workspace()->id,
        'team_id' => $team->id,
        'name' => 'Bot',
        'external_id' => Str::random(20),
        'slug' => Str::random(14),
        'is_public' => false,
        'source' => 'dashboard',
    ], $overrides));
}

/** A named agent on $team (the owner's first team when omitted). */
function makeTeamAgent(User $user, string $name, ?Team $team = null): Agent
{
    return createAgent($user, ['name' => $name, 'team_id' => ($team ?? ownerTeam($user))->id, 'source' => 'api']);
}

/**
 * A teammate of the owner's workspace on $team (the owner's primary team when
 * omitted), under a new role granting exactly $permissions. Their current team
 * is that team, so the permission helpers resolve it.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentExternalIds  the agents the role reaches when not $allAgents
 */
function teammateWithPermissions(User $owner, array $permissions, ?Team $team = null, bool $allAgents = true, array $agentExternalIds = []): User
{
    $team ??= ownerTeam($owner);

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: $allAgents,
        agentExternalIds: $agentExternalIds,
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

/**
 * Switch off the agent's workspace the way the admin panel does: deactivate
 * its owner, which also stops its agents ({@see WorkspaceActivation}).
 */
function deactivateWorkspaceOf(Agent $agent, string $reason = 'Unpaid invoices'): void
{
    app(DeactivateAccountAction::class)->execute(
        $agent->workspace->owner ?? throw new LogicException('A workspace always has an owner.'),
        $reason,
    );
}

/** Switch the agent's workspace back on, as the admin panel does. */
function reactivateWorkspaceOf(Agent $agent): void
{
    app(ReactivateAccountAction::class)->execute(
        $agent->workspace->owner ?? throw new LogicException('A workspace always has an owner.'),
    );
}

/**
 * Every call to any model goes to this fake client.
 */
function bindFakeLlmProvider(LlmProviderInterface $provider): void
{
    app()->instance(LlmProviders::class, new FakeLlmProviders($provider));
}

/**
 * Give AI provider accounts a test key in config (Settings → AI providers),
 * so their chat models and services can be called. With none named, every
 * account gets one.
 */
function connectAiProviders(AiProvider ...$providers): void
{
    foreach ($providers === [] ? AiProvider::cases() : $providers as $provider) {
        config([$provider->apiKeyConfig() => 'test-key']);
    }
}

/**
 * A model's entry as the models.dev catalog lists it (what `llms.capabilities`
 * keeps): its effort values, if any, and whether it takes a temperature.
 *
 * @param  list<string>|null  $efforts
 * @return array<string, mixed>
 */
function modelsDevEntry(?array $efforts, ?bool $temperature = null, bool $reasoning = true): array
{
    return array_filter([
        'reasoning' => $reasoning,
        'reasoning_options' => $efforts !== null ? [['type' => 'effort', 'values' => $efforts]] : null,
        'temperature' => $temperature,
    ], static fn (mixed $value): bool => $value !== null);
}

/**
 * A catalog model on a new provider (an OpenAI-compatible test server unless
 * a kind is given). A provider with an account gets a test key in config, as
 * a connected one has.
 *
 * @param  array<string, mixed>  $attributes
 */
function testLlm(string $name = 'test-model', LlmProviderKind $kind = LlmProviderKind::Custom, array $attributes = []): Llm
{
    if (($account = AiProvider::forChatKind($kind)) instanceof AiProvider) {
        connectAiProviders($account);
    }

    return Llm::factory()
        ->for(LlmProvider::factory()->kind($kind), 'provider')
        ->create(['name' => $name, ...$attributes]);
}

/**
 * Stub the LLM provider's chat-completion HTTP call so analysis tests get a
 * deterministic assistant message instead of hitting a real model.
 */
function fakeLlmCompletion(string $content): void
{
    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => $content]]],
        ]),
    ]);
}

/**
 * An id that is free in both `users` and `chatters`, so a test can create a
 * staff account and a chatter with the same id and prove the two never mix.
 * Taking the next value of both sequences means neither table can hold it.
 */
function sharedStaffAndChatterId(): int
{
    return (int) DB::scalar("select greatest(nextval(pg_get_serial_sequence('users', 'id')), nextval(pg_get_serial_sequence('chatters', 'id')))");
}

/**
 * A job setting as the queue reads it: a value set on the instance wins, then
 * the class attribute (`#[Queue]`, `#[Timeout]` …), then the property. A class
 * name is read without running its constructor.
 *
 * @param  object|class-string  $job
 * @param  class-string  $attribute
 */
function jobSetting(object|string $job, string $attribute, string $property): mixed
{
    $job = is_object($job) ? $job : new ReflectionClass($job)->newInstanceWithoutConstructor();

    $reader = new class
    {
        use ReadsClassAttributes {
            getAttributeValue as public;
        }
    };

    return $reader->getAttributeValue($job, $attribute, $property);
}

/**
 * The queue a job goes to when the dispatch names none; null for a class
 * that picks its queue in the constructor.
 *
 * @param  object|class-string  $job
 */
function jobQueue(object|string $job): ?string
{
    return jobSetting($job, Queue::class, 'queue');
}

/**
 * A job's timeout in seconds.
 *
 * @param  object|class-string  $job
 */
function jobTimeout(object|string $job): ?int
{
    return jobSetting($job, Timeout::class, 'timeout');
}

/**
 * A setting as the next request reads it. Settings classes are scoped
 * instances, so the cached copy is forgotten first and the value comes from
 * storage.
 *
 * @param  class-string  $class  a settings class (App\Admin\Settings)
 */
function savedSetting(string $class, string $property): mixed
{
    app()->forgetScopedInstances();

    return app($class)->{$property};
}

/**
 * Save settings as the admin panel does (saving copies every saved setting
 * into config again), then forget the scoped instances so the next read sees
 * the new values.
 *
 * @param  class-string  $class  a settings class (App\Admin\Settings)
 * @param  array<string, mixed>  $values
 */
function saveSettings(string $class, array $values): void
{
    app($class)->fill($values)->save();

    app()->forgetScopedInstances();
}

/**
 * One hourly analytics rollup row of the agent (no country, no model), with
 * the given counters.
 *
 * @param  array<string, mixed>  $values
 */
function analyticsRollup(Agent $agent, string $bucketHour, array $values = []): void
{
    DB::connection(config('chatvia.analytics.connection'))
        ->table('analytics_agent_hourly_rollups')
        ->insert([
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'bucket_hour' => $bucketHour,
            'country_code' => '--',
            'llm_id' => 0,
            ...$values,
        ]);
}

/**
 * The test, with its next API requests sent as this bearer token. Use it when
 * one test calls the API as several people: the app (and its auth guards)
 * lives on across requests in a test, so a guard would keep the first
 * caller it resolved. Forgetting the guards makes each token count.
 */
function withApiToken(string $token): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken($token);
}

/**
 * Whether the program set as `services.bin.{$name}` (an absolute path, or a
 * name looked up on PATH) can run here. Tests of pdftotext, pandoc, catdoc
 * and ffmpeg skip without it; CI installs them.
 */
function programInstalled(string $name): bool
{
    $binary = (string) config("services.bin.{$name}");

    return $binary !== '' && (is_executable($binary) || (new ExecutableFinder)->find($binary) !== null);
}

/**
 * Workspace topics with the given names, attached to the agent; their ids
 * by name.
 *
 * @param  list<string>  $names
 * @return array<string, int>
 */
function attachAgentTopics(Agent $agent, array $names): array
{
    $ids = [];

    foreach ($names as $name) {
        $topic = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => $name, 'description' => "About {$name}"]);
        $agent->topics()->attach($topic->id);
        $ids[$name] = $topic->id;
    }

    return $ids;
}

/**
 * Which embedding provider and model the offline evals (tests/Eval) embed
 * with, as env overrides for one run: EVAL_EMBEDDING_PROVIDER (an
 * EmbeddingProvider value, e.g. "openrouter") and EVAL_EMBEDDING_MODEL (that
 * provider's model, e.g. "qwen/qwen3-embedding-8b"). Unset, the configured
 * provider runs — in the testing environment that is the config default, not
 * what the admin panel of an install has.
 */
function evalEmbeddingOverrides(): void
{
    if (filled($provider = env('EVAL_EMBEDDING_PROVIDER'))) {
        config(['chatvia.ai.models.embedding' => $provider]);
    }

    $configured = EmbeddingProvider::tryFrom((string) config('chatvia.ai.models.embedding'));

    if (filled($model = env('EVAL_EMBEDDING_MODEL')) && $configured !== null) {
        config([$configured->modelConfig() => $model]);
    }
}
