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

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunksAction;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\PageGroupData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\LlmProviders;
use Domain\Llm\Services\ModelsDevCatalog;
use Domain\Llm\Services\TaskModels;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ReadsClassAttributes;
use Illuminate\Support\Uri;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\ExecutableFinder;
use Tests\RefreshTestDatabases;
use Tests\Support\Files\TempTestFiles;
use Tests\Support\Identity\SharedId;
use Tests\Support\Knowledge\Document\OldOffice\WordBinaryFile;
use Tests\Support\License\LicenseKeys;
use Tests\Support\Llm\FakeLlmProviders;
use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->use(RefreshTestDatabases::class)
    // The temp files a test made with tempTestFile() go, passed or failed.
    ->afterEach(fn () => TempTestFiles::delete())
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
 * A role on $team granting exactly $permissions, made the way Settings → Teams
 * makes one. A new team has only the owner role, which nobody can be invited
 * to, so a test that invites or adds a teammate needs one of these.
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $agentExternalIds  the agents the role reaches when not $allAgents
 */
function teamRole(Team $team, string $name = 'Support', array $permissions = ['agent.chat.view'], bool $allAgents = true, array $agentExternalIds = []): Role
{
    return app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: $name,
        permissions: $permissions,
        allAgents: $allAgents,
        agentExternalIds: $agentExternalIds,
    ));
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

    $role = teamRole($team, 'Role-'.Str::random(10), $permissions, $allAgents, $agentExternalIds);

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

/**
 * Every agent permission but $permission, for a teammate who holds all of
 * them except the one under test.
 *
 * @return list<string>
 */
function allAgentPermissionsBut(string $permission): array
{
    return collect(config('team-permissions.permissions'))
        ->keys()
        ->filter(fn (string $code): bool => Str::startsWith($code, 'agent.') && $code !== $permission)
        ->values()
        ->all();
}

/** An admin of the install, as the admin panel's guard loads one. */
function adminUser(): User
{
    return User::factory()->admin()->create()->fresh();
}

/**
 * A Filament page (or other Livewire component) of the admin panel, opened by
 * $admin, or by a new admin when none is given.
 *
 * @param  class-string  $page
 */
function adminPage(string $page, ?User $admin = null): Testable
{
    return Livewire::actingAs($admin ?? adminUser(), 'admin')->test($page);
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

        // A chat account on a resource of its own (Azure OpenAI) calls nothing without its endpoint.
        if ($provider->needsBaseUrl() && $provider->chatKind() instanceof LlmProviderKind) {
            config([$provider->baseUrlConfig() => "https://{$provider->value}.test"]);
        }
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
 * Forgets the empty models.dev catalog every test starts with, so the next
 * lookup downloads it, from the test's fake.
 */
function forgetModelsDevCatalog(): void
{
    Cache::memo()->forget(ModelsDevCatalog::CACHE_KEY);
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
 * Turn source summaries on with a catalog model that reads PDF files as the
 * model for them, or turn them off.
 */
function useSourceSummaries(bool $on = true, string $name = 'mistral-large-latest', LlmProviderKind $kind = LlmProviderKind::Mistral): ?Llm
{
    if (! $on) {
        config(['chatvia.ai.source_summaries.enabled' => false]);

        return null;
    }

    $model = testLlm($name, $kind, [
        'capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'pdf']]],
    ]);
    config(['chatvia.ai.source_summaries.enabled' => true, 'chatvia.ai.source_summaries.model' => (string) $model->id]);

    return $model;
}

/**
 * Turn chat file uploads on with a catalog model that reads PDF files and
 * images as the model for them, or turn them off.
 */
function useChatFiles(bool $on = true): ?Llm
{
    if (! $on) {
        config(['chatvia.ai.chat_files.enabled' => false]);

        return null;
    }

    $model = testLlm('mistral-medium-latest', LlmProviderKind::Mistral, [
        'capabilities' => ['reasoning' => false, 'modalities' => ['input' => ['text', 'image', 'pdf']]],
    ]);
    config(['chatvia.ai.chat_files.enabled' => true, 'chatvia.ai.chat_files.model' => (string) $model->id]);

    return $model;
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
 * An id that is free in both `users` and `chatters` ({@see SharedId}), so a
 * test can create a staff account and a chatter with the same id and prove
 * the two never mix.
 */
function sharedStaffAndChatterId(): int
{
    return SharedId::staffAndChatter();
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
 * Gives this install an Enterprise license: an Enterprise test key activated
 * for the host of `app.url`, saved as the admin panel saves it, so the flag
 * survives every later SettingsConfig apply (before jobs, after saves).
 */
function withEnterpriseLicense(): void
{
    LicenseKeys::trust();

    saveSettings(BrandSettings::class, [
        'license_key' => LicenseKeys::enterpriseFor((string) Uri::of((string) config('app.url'))->host()),
    ]);
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
    return collect($names)
        ->mapWithKeys(fn (string $name): array => [$name => Topic::factory()->attachedTo($agent)->create(['topic' => $name, 'description' => "About {$name}"])->id])
        ->all();
}

/**
 * A stored embedding written by hand, as pgvector text: $value in every
 * dimension, at the size the install stores (EMBEDDING_DIMENSIONS), never a
 * fixed 1024.
 */
function embeddingVector(float $value = 0.1): string
{
    return '['.implode(',', array_fill(0, app(EmbeddingStorage::class)->dimensions(), $value)).']';
}

/**
 * A temp file with $content, named with the extension of $filename. It is
 * deleted after the test, whether the test passed or failed.
 */
function tempTestFile(string $filename, string $content = ''): string
{
    return deleteAfterTest(create_temp_file($filename, $content));
}

/**
 * Deletes $path after the test, whether the test passed or failed: for a
 * file a builder wrote ({@see WordBinaryFile::save()}). Returns $path.
 */
function deleteAfterTest(string $path): string
{
    return TempTestFiles::track($path);
}

/** A PDF of tests/Fixtures/pdf (plain, or locked with an owner or user password). */
function pdfFixture(string $name): string
{
    return base_path("tests/Fixtures/pdf/{$name}.pdf");
}

/**
 * Stores these pages (page 1 first) as the source's chunks the way ingestion
 * does: through CreateSourceChunksAction, as one group, embedded by the bound
 * embeddings provider.
 *
 * @param  list<string>  $pages
 */
function storeSourceChunks(SourceFile|SourceText|SourceWebsite $source, array $pages, ChunkEnhancement $enhancement = ChunkEnhancement::Off): void
{
    app(CreateSourceChunksAction::class)->execute(new ChunkPagesData(
        source: $source,
        group: new PageGroupData(PageData::fromParts($pages)),
        enhancement: $enhancement,
    ));
}

/**
 * Swaps the offline fakes for the real models an eval (tests/Eval) measures.
 * A provider key given on the command line, named after the provider's
 * config block (SCALEWAYAI_API_KEY, OPENROUTER_API_KEY, COHERE_API_KEY,
 * GEMINI_API_KEY, VOYAGE_API_KEY…), goes into config, as the admin panel puts
 * it there; the EVAL_EMBEDDING_* and EVAL_RERANK_* overrides apply
 * ({@see evalEmbeddingOverrides()}, {@see evalRerankOverrides()});
 * outside calls are allowed, and retried after a rate limit; and the
 * configured embeddings provider and reranker are bound, probed once. Returns the bound embeddings provider.
 *
 * @throws RuntimeException before any request, when no key is given
 * @throws Throwable when the real embeddings cannot be reached (a wrong key, no network)
 */
function evalUseRealModels(): EmbeddingsProviderInterface
{
    $given = 0;

    foreach (AiProvider::cases() as $provider) {
        $variable = Str::upper(Str::between($provider->apiKeyConfig(), 'services.', '.')).'_API_KEY';

        if (filled($key = env($variable))) {
            config([$provider->apiKeyConfig() => $key]);
            $given++;
        }
    }

    if ($given === 0) {
        throw new RuntimeException('needs a real key: SCALEWAYAI_API_KEY=… (or OPENROUTER_API_KEY=…; COHERE_API_KEY=… for the reranker) vendor/bin/pest --testsuite=Eval');
    }

    evalEmbeddingOverrides();
    evalRerankOverrides();
    evalChatOverrides();
    Http::allowStrayRequests();

    // Free API tiers cap tokens per minute: wait and ask again after a 429.
    Http::globalMiddleware(GuzzleMiddleware::retry(
        static fn (int $retries, RequestInterface $request, ?ResponseInterface $response = null): bool => $retries < 10 && $response?->getStatusCode() === 429,
        static fn (): int => 20_000,
    ));

    app()->forgetInstance(EmbeddingsProviderInterface::class);
    $embeddings = app(EmbeddingsProviderInterface::class);
    $embeddings->embedQuery('health check');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    app()->forgetInstance(Reranker::class);
    app()->instance(Reranker::class, app(Reranker::class));

    return $embeddings;
}

/**
 * Which embedding provider and model the offline evals (tests/Eval) embed
 * with, as env overrides for one run: EVAL_EMBEDDING_PROVIDER (an
 * EmbeddingProvider value, e.g. "openrouter") and EVAL_EMBEDDING_MODEL (that
 * provider's model, e.g. "qwen/qwen3-embedding-8b"). Unset, the configured
 * provider runs — in the testing environment that is the config default, not
 * what the admin panel of an install has.
 */
/**
 * Which reranker the offline evals (tests/Eval) use, as env overrides for one
 * run: EVAL_RERANK_URL (a server with the common /rerank API, such as
 * https://api.jina.ai/v1), EVAL_RERANK_MODEL and EVAL_RERANK_KEY; or
 * EVAL_RERANKER, a driver of RerankerFactory::DRIVERS ("none" measures an
 * install without a reranker). Unset, the configured reranker runs.
 */
function evalRerankOverrides(): void
{
    if (filled($driver = env('EVAL_RERANKER'))) {
        config(['chatvia.ai.reranker' => $driver]);
    }

    if (blank($url = env('EVAL_RERANK_URL'))) {
        return;
    }

    config([
        'chatvia.ai.reranker' => 'custom',
        'chatvia.ai.rerank.custom.url' => $url,
        'chatvia.ai.rerank.custom.model' => env('EVAL_RERANK_MODEL'),
        'chatvia.ai.rerank.custom.key' => env('EVAL_RERANK_KEY'),
    ]);
}

/**
 * Which chat model the offline evals (tests/Eval) use for the app's own tasks
 * (chunk enhancement, follow-up rewrites…), as env overrides for one run:
 * EVAL_CHAT_MODEL (a model id, e.g. "google/gemma-4-26b-a4b-it") on
 * EVAL_CHAT_PROVIDER (an LlmProviderKind value, default "openrouter"; its key
 * given as for the embeddings). Unset, the testing catalog's model runs,
 * which is on Scaleway.
 */
function evalChatOverrides(): void
{
    if (blank($model = env('EVAL_CHAT_MODEL'))) {
        return;
    }

    $llm = Llm::factory()
        ->for(LlmProvider::factory()->kind(LlmProviderKind::from((string) env('EVAL_CHAT_PROVIDER', LlmProviderKind::OpenRouter->value))), 'provider')
        ->create(['name' => $model]);

    config([TaskModels::SHARED_KEY => (string) $llm->id]);
    Llm::flushCatalogCache();
}

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
