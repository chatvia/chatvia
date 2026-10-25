<?php

use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Pages\Setup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SetupStatus;
use App\Admin\Settings\StorageSettings;
use App\Admin\Status\Data\ServiceStatusData;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceState;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Jobs\SyncBuiltinConnectorsJob;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Enums\PgTextsearchState;
use Domain\Knowledge\Retrieval\Services\PgTextsearchSupport;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelsDevCatalog;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Enums\GridDirection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Tests\Support\Admin\FakeRedisAndHorizon;

/**
 * The admin setup of a new install: every panel page leads to it until it is
 * done or skipped. "AI provider": the main provider and its key, checked by reading
 * its model list; a provider that makes no embeddings of the stored size
 * also needs an embedding service and its key. "Models": the chat models
 * and the model for quick tasks (the provider's recommended ones picked),
 * the embedding model (OpenRouter's from its list) checked with a test
 * embedding, and the reranker checked with a test question. Each key is
 * saved once, in
 * Settings → AI providers ({@see CompleteSetupAction}).
 */
beforeEach(function (): void {
    $this->admin = adminUser();
    $this->dimensions = app(EmbeddingStorage::class)->dimensions();

    Filament::setCurrentPanel('admin');
    // The server step checks Redis and the queue: answer them offline.
    FakeRedisAndHorizon::bind();
    Queue::fake([SyncBuiltinConnectorsJob::class]);
    setupFakeOpenRouterEmbeddingModels();
    setupNotDone();

    // A new install: no key is saved yet.
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

function setupNotDone(): void
{
    $platform = app(PlatformSettings::class);
    $platform->setup_status = null;
    $platform->save();
}

/** The setup page past its server step, with every service running. */
function setupPage(User $admin): Testable
{
    return adminPage(Setup::class, $admin)
        ->set('server', setupServicesRunning())
        ->goToNextWizardStep()
        ->assertWizardCurrentStep(2);
}

/**
 * The server step's results, every service running but those given.
 *
 * @param  array<string, ServiceState>  $states  Service value => state.
 * @return array<string, array<string, mixed>>
 */
function setupServicesRunning(array $states = []): array
{
    return collect([Service::Database, Service::Redis, Service::Queue, Service::Scheduler, Service::Realtime])
        ->mapWithKeys(fn (Service $service): array => [$service->value => new ServiceStatusData(
            $states[$service->value] ?? ServiceState::Running,
            ($states[$service->value] ?? ServiceState::Running) === ServiceState::Down ? 'Horizon is not running.' : 'Answers.',
            hint: 'Start it with "php artisan horizon".',
        )->toArray()])
        ->all();
}

/** The Scaleway model list, and its embeddings of a given size. */
function setupFakeScaleway(int $dimensions): void
{
    Http::fake([
        'api.scaleway.ai/*/models' => Http::response(['data' => [['id' => 'gpt-oss-120b'], ['id' => 'gemma-4-26b-a4b-it']]]),
        'api.scaleway.ai/*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $dimensions, 0.1)]]]),
    ]);
}

/**
 * OpenRouter's public list of embedding models (two, and a batch one it
 * hides). The setup opens on OpenRouter, so it reads the list at once.
 */
function setupFakeOpenRouterEmbeddingModels(): void
{
    Http::fake([
        'openrouter.ai/api/v1/embeddings/models' => Http::response(['data' => [
            ['id' => 'openai/text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 Small'],
            ['id' => 'baai/bge-m3', 'name' => 'BAAI: bge-m3'],
            ['id' => 'openai/text-embedding-3-small:batch', 'name' => 'OpenAI: Text Embedding 3 Small (batch)'],
        ]]),
    ]);
}

/** The OpenRouter model list (one chat model, one that cannot chat), its embeddings and its reranking. */
function setupFakeOpenRouter(int $dimensions): void
{
    Http::fake([
        'openrouter.ai/api/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $dimensions, 0.1)]]]),
        'openrouter.ai/api/v1/rerank' => Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9]]]),
        'openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]),
        'openrouter.ai/api/v1/models/user' => Http::response(['data' => [
            [
                'id' => 'meta-llama/llama-4-maverick',
                'name' => 'Llama 4 Maverick',
                'context_length' => 1_048_576,
                'pricing' => ['prompt' => '0.00000015', 'completion' => '0.0000006'],
                'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
                'supported_parameters' => ['tools', 'temperature'],
            ],
            [
                'id' => 'openai/text-embedding-3-small',
                'name' => 'Text embedding',
                'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['embeddings']],
            ],
        ]]),
    ]);
}

/** The Groq model list, and Cohere embeddings and reranking. */
function setupFakeGroqAndCohere(int $dimensions): void
{
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
        'api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, $dimensions, 0.1)]]]),
        'api.cohere.com/v2/rerank' => Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9]]]),
    ]);
}

it('leads every panel page to the setup until it is done, then never again', function (): void {
    $this->actingAs($this->admin, 'admin');

    $this->get(Filament::getUrl())->assertRedirect(route('filament.admin.setup'));
    $this->get(route('filament.admin.auth.profile'))->assertOk();
    $this->get(route('filament.admin.setup'))->assertOk()->assertSee('Welcome to');

    $platform = app(PlatformSettings::class);
    $platform->setup_status = SetupStatus::Completed->value;
    $platform->save();

    $this->get(Filament::getUrl())->assertOk();
    $this->get(route('filament.admin.setup'))->assertRedirect(Filament::getUrl());
});

it('warns that the app does not work before the setup is skipped', function (): void {
    setupPage($this->admin)
        ->mountAction('skip')
        ->assertMountedActionModalSee(['ChatVia.ai does not work without these', 'an AI provider for chat', 'an embedding service', 'agents cannot answer'])
        ->callMountedAction()
        ->assertRedirect(Filament::getUrl());

    expect(savedSetting(PlatformSettings::class, 'setup_status'))->toBe('skipped');
});

it('says what a quick task is, one task or every one', function (): void {
    setupPage($this->admin)
        ->mountAction('quickTasks', ['task' => 'guardrails'])
        ->assertMountedActionModalSee(['Guardrails', 'Checks each message for prompt injection', 'The model for quick tasks does it.'])
        ->assertMountedActionModalDontSee('Conversation titles');

    setupPage($this->admin)
        ->mountAction('quickTasks')
        ->assertMountedActionModalSee(['Quick tasks', 'During a chat', 'Follow-up questions', 'Conversation titles', 'Tables', 'Settings → AI models'])
        ->assertMountedActionModalDontSee(['Image descriptions', 'Evaluation judge', 'Translating agent texts']);
});

it('can be finished after a skip, from Settings', function (): void {
    $platform = app(PlatformSettings::class);
    $platform->setup_status = SetupStatus::Skipped->value;
    $platform->save();

    $this->actingAs($this->admin, 'admin');

    $this->get(Filament::getUrl())->assertOk();
    $this->get(SettingsOverview::getUrl())->assertSee('Finish the setup');
    $this->get(route('filament.admin.setup'))->assertOk()->assertSee('Back to the admin panel')->assertDontSee('Skip the setup for now');
});

it('opens on the server step and checks the services ChatVia.ai runs on', function (): void {
    adminPage(Setup::class, $this->admin)
        ->assertWizardCurrentStep(1)
        ->assertSee('Checking each service…')
        ->call('checkServer')
        ->assertSet('server', fn (array $server): bool => array_keys($server) === ['database', 'redis', 'queue', 'scheduler', 'realtime', 'model_details'])
        ->assertSee(['PostgreSQL', 'Redis', 'Laravel Horizon', 'Scheduler', 'Laravel Reverb', 'models.dev']);
});

it('links the models.dev row to the site once its list is read', function (): void {
    adminPage(Setup::class, $this->admin)
        ->set('server', [...setupServicesRunning(), Service::ModelDetails->value => new ServiceStatusData(ServiceState::Running, 'Model details loaded.')->toArray()])
        ->assertSeeHtml('<a href="https://models.dev" target="_blank" rel="noopener noreferrer" class="cv-service__link">models.dev</a>');
});

it('says why pg_textsearch matters and what to do to get it', function (): void {
    $state = app(PgTextsearchSupport::class)->state();

    $page = adminPage(Setup::class, $this->admin)->set('server', setupServicesRunning())->assertSee('Everything runs.');

    match ($state) {
        PgTextsearchState::Active => $page->assertDontSee(['Keyword search', 'pg_textsearch']),
        PgTextsearchState::NotInstalled => $page->assertSee(['Install pg_textsearch (recommended)', 'ranks the words with BM25', 'releases page', 'the built-in search works'])->assertDontSee('Turn on pg_textsearch'),
        PgTextsearchState::NotPreloaded => $page->assertSee(['Load pg_textsearch when PostgreSQL starts', 'shared_preload_libraries']),
        PgTextsearchState::ReadyToEnable => $page->assertSee('Turn on pg_textsearch'),
        PgTextsearchState::SwitchedOff => $page->assertSee('SEARCH_LEXICAL_DRIVER'),
    };
});

it('asks before going on with a service down, and goes on once agreed', function (): void {
    adminPage(Setup::class, $this->admin)
        ->set('server', setupServicesRunning(['queue' => ServiceState::Down]))
        ->assertSee(['One service is down.', 'Horizon is not running.', 'php artisan horizon'])
        ->goToNextWizardStep()
        ->assertActionMounted('serverDown')
        ->assertMountedActionModalSee(['Continue with a service down?', 'Down: Laravel Horizon.', 'Continue anyway', 'Fix it first'])
        ->callMountedAction()
        ->assertWizardCurrentStep(2)
        ->assertSet('serverDownConfirmed', true);
});

it('goes on without asking when a service only needs a look', function (): void {
    adminPage(Setup::class, $this->admin)
        ->set('server', setupServicesRunning(['scheduler' => ServiceState::Warning]))
        ->assertSee('Everything answers. One service needs a look.')
        ->goToNextWizardStep()
        ->assertActionNotMounted('serverDown')
        ->assertWizardCurrentStep(2);
});

it('starts on OpenRouter, lists the providers by name, and says what each provider gives', function (): void {
    setupPage($this->admin)
        ->assertWizardCurrentStep(2)
        ->assertFormSet(['provider' => 'openrouter', 'embedding_provider' => 'openrouter'])
        ->assertSeeInOrder(['Anthropic', 'Google Gemini', 'Groq', 'Mistral', 'OpenAI', 'OpenRouter', 'Scaleway AI', 'Your own server'])
        ->assertSee(['Open models hosted in Europe.', 'Your own server'])
        ->assertSeeHtml(['<span class="cv-chip">speech to text</span>', '<span class="cv-chip">reranking</span>', 'images/llms/gpt.svg'])
        // Embeddings are picked in the Models step, on the provider's own.
        ->assertSee('OpenRouter makes them with the same key, or pick another service.')
        ->assertFormFieldVisible('embedding_provider')
        ->assertFormFieldHidden('embedding_api_key');
});

it('checks the key with the provider\'s model list before the next step', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(3)
        ->assertSee('Connected to Scaleway AI')
        // The recommended model the provider lists (gpt-oss-120b is not
        // one), for chat, quick tasks and image descriptions.
        ->assertFormSet([
            'chat_models' => ['gemma-4-26b-a4b-it'],
            'default_model' => 'gemma-4-26b-a4b-it',
            'quick_task_model' => 'gemma-4-26b-a4b-it',
            'describe_images' => true,
            'vision_model' => 'gemma-4-26b-a4b-it',
        ])
        ->assertFormFieldVisible('vision_model');

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/models')
        && $request->header('Authorization') === ['Bearer scw-test']);
});

it('picks a few OpenRouter chat models from different makers, and a small one for quick tasks', function (): void {
    $model = fn (string $id, bool $tools = true): array => [
        'id' => $id,
        'name' => $id,
        'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
        'supported_parameters' => $tools ? ['tools'] : ['temperature'],
    ];
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'sk-or-v1-tes...']]), 'openrouter.ai/api/v1/models/user' => Http::response(['data' => [
        $model('google/gemma-4-26b-a4b-it'),
        $model('openai/gpt-oss-120b'),
        $model('deepseek/deepseek-v4.1-flash', tools: false),
        $model('deepseek/deepseek-v4-flash'),
        $model('mistralai/mistral-large-2512'),
        $model('openai/gpt-4.1'),
        $model('openai/gpt-6-luna'),
    ]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3)
        // One per maker, default first; a model that cannot call tools is
        // passed over for the next of its maker, a maker with none listed
        // (Qwen) is left out, and a model that is no preset (gpt-oss-120b,
        // Mistral Large) is not picked.
        ->assertFormSet([
            'chat_models' => ['deepseek/deepseek-v4-flash', 'openai/gpt-4.1', 'google/gemma-4-26b-a4b-it'],
            'default_model' => 'deepseek/deepseek-v4-flash',
            'quick_task_model' => 'openai/gpt-6-luna',
            'describe_images' => true,
            'vision_model' => 'openai/gpt-6-luna',
        ]);
});

it('stops on the first step when OpenRouter refuses the key, although its model list is public', function (): void {
    Http::fake([
        'openrouter.ai/api/v1/key' => Http::response(['error' => ['message' => 'User not found.', 'code' => 401]], 401),
        'openrouter.ai/api/v1/models/user' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-wrong'])
        ->goToWizardStep(3)
        ->assertHasFormErrors(['api_key' => 'OpenRouter refused the API key. Check that you copied all of it.'])
        ->assertDispatched('form-validation-error')
        ->assertWizardCurrentStep(2);
});

it('stops on the first step when the provider refuses the key', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'wrong-key'])
        ->goToWizardStep(3)
        ->assertHasFormErrors(['api_key'])
        ->assertWizardCurrentStep(2);
});

it('lets the admin turn image descriptions off, although a recommended model reads images', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['describe_images' => true])
        ->fillForm(['describe_images' => false])
        ->assertFormFieldHidden('vision_model')
        ->assertSee('Off: pictures are not described.')
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'describe_images'))->toBeFalse()
        ->and(savedSetting(AiSettings::class, 'images_model'))->toBeNull();
});

it('asks for a model once image descriptions are turned on', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['vision_model' => null])
        ->call('complete')
        ->assertHasFormErrors(['vision_model' => 'required']);
});

it('saves the Scaleway key once, for chat and for embeddings', function (): void {
    setupFakeScaleway($this->dimensions);
    // A demo agent made before any model was added.
    $agent = createAgent(User::factory()->create());
    $agent->forceFill(['llm_id' => null])->saveQuietly();

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['chat_models' => ['gemma-4-26b-a4b-it']])
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $model = Llm::query()->whereKey(savedSetting(AiSettings::class, 'default_agent_model'))->sole();
    $provider = $model->provider;

    expect($provider->kind)->toBe(LlmProviderKind::Scaleway)
        ->and(LlmProvider::query()->where('kind', LlmProviderKind::Scaleway)->count())->toBe(1)
        ->and($provider->api_key)->toBeNull()
        ->and($provider->base_url)->toBeNull()
        ->and($model->name)->toBe('gemma-4-26b-a4b-it')
        ->and(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('scw-test')
        // The default embedding service and model stay unsaved.
        ->and(savedSetting(AiSettings::class, 'embedding_provider'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'scaleway_embedding_model'))->toBeNull()
        ->and(savedSetting(PlatformSettings::class, 'setup_status'))->toBe('completed')
        ->and($model->isAvailable())->toBeTrue()
        // The recommended small model does the quick tasks; the tasks that
        // need a large model use the chat model (here the same one).
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBe((string) $model->id)
        ->and(savedSetting(AiSettings::class, 'eval_judge_model'))->toBe((string) $model->id)
        ->and(savedSetting(AiSettings::class, 'guardrails_model'))->toBeNull()
        // It gets the default model.
        ->and($agent->refresh()->llm_id)->toBe($model->id);

    Queue::assertPushed(SyncBuiltinConnectorsJob::class);
});

it('makes the embeddings with the OpenRouter key, with a model from its list', function (): void {
    setupFakeOpenRouter($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter'])
        // OpenRouter makes the embeddings with the same key.
        ->assertFormFieldHidden('embedding_api_key')
        // The Scaleway model it started with goes with Scaleway.
        ->assertFormSet(['embedding_provider' => 'openrouter', 'embedding_model' => 'openai/text-embedding-3-small'])
        ->fillForm(['api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(3)
        // No recommended model is listed: the first model that calls tools
        // is, and quick tasks use it too.
        ->assertFormSet(['chat_models' => ['meta-llama/llama-4-maverick'], 'quick_task_model' => null, 'describe_images' => false, 'vision_model' => null])
        ->assertFormFieldHidden('vision_model')
        ->assertSee('None of the recommended models of this provider reads images, so image descriptions are off.')
        ->assertSee(['OpenRouter makes them with the same key, or pick another service.', 'OpenAI text-embedding-3-small — Good and cheap. The default.', 'BAAI: bge-m3'])
        ->assertDontSee('Text Embedding 3 Small (batch)')
        ->fillForm(['embedding_model' => 'qwen/qwen3-embedding-8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $model = Llm::query()->whereKey(savedSetting(AiSettings::class, 'default_agent_model'))->sole();

    expect($model->provider->kind)->toBe(LlmProviderKind::OpenRouter)
        ->and($model->name)->toBe('meta-llama/llama-4-maverick')
        ->and(savedSetting(AiProviderSettings::class, 'openrouter_api_key'))->toBe('sk-or-test')
        ->and(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('openrouter')
        ->and(savedSetting(AiSettings::class, 'openrouter_embedding_model'))->toBe('qwen/qwen3-embedding-8b')
        // No recommended model that reads images: image descriptions are off.
        ->and(savedSetting(AiSettings::class, 'describe_images'))->toBeFalse();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/embeddings'
        && $request->header('Authorization') === ['Bearer sk-or-test']
        && $request['model'] === 'qwen/qwen3-embedding-8b'
        && $request['dimensions'] === $this->dimensions);
});

it('asks for an embedding service in the Models step when the provider makes no embeddings', function (): void {
    setupFakeGroqAndCohere($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(3)
        ->assertSee('Groq does not make them, so pick a service that does.')
        ->fillForm(['embedding_provider' => 'cohere', 'embedding_api_key' => 'co-test'])
        ->assertFormSet(['embedding_model' => config('services.cohere.embedding_model')])
        ->fillForm(['embedding_model' => 'embed-multilingual-v3.0'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiProviderSettings::class, 'groq_api_key'))->toBe('gsk-test')
        ->and(savedSetting(AiProviderSettings::class, 'cohere_api_key'))->toBe('co-test')
        ->and(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere')
        ->and(savedSetting(AiSettings::class, 'cohere_embedding_model'))->toBe('embed-multilingual-v3.0');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/embed'
        && $request->header('Authorization') === ['Bearer co-test']);
});

it('stops on the models step when the embedding service refuses its key', function (): void {
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
        'api.cohere.com/*' => Http::response(['message' => 'invalid api token'], 401),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test'])
        ->goToWizardStep(3)
        ->fillForm(['embedding_provider' => 'cohere', 'embedding_api_key' => 'wrong'])
        ->goToWizardStep(4)
        ->assertHasFormErrors(['embedding_api_key'])
        ->assertWizardCurrentStep(3);
});

it('makes the embeddings with another provider than the chat, with its own key', function (): void {
    setupFakeOpenRouter($this->dimensions);
    Http::fake(['api.jina.ai/*' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $this->dimensions, 0.1)]]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['embedding_provider' => 'openrouter'])
        ->fillForm(['embedding_provider' => 'jina'])
        ->assertFormFieldVisible('embedding_api_key')
        ->fillForm(['embedding_api_key' => 'jina-key'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('jina')
        ->and(savedSetting(AiProviderSettings::class, 'jina_api_key'))->toBe('jina-key')
        ->and(savedSetting(AiProviderSettings::class, 'openrouter_api_key'))->toBe('sk-or-test');
})->skip(fn (): bool => ! in_array(EmbeddingProvider::Jina, EmbeddingProvider::fitting(), true), 'Jina does not return the stored vector size.');

it('makes the embeddings with Voyage AI, with its own key', function (): void {
    setupFakeOpenRouter($this->dimensions);
    Http::fake(['api.voyageai.com/*' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $this->dimensions, 0.1)]]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->fillForm(['embedding_provider' => 'voyage'])
        ->assertFormFieldVisible('embedding_api_key')
        ->fillForm(['embedding_api_key' => 'pa-test'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('voyage')
        ->and(savedSetting(AiProviderSettings::class, 'voyage_api_key'))->toBe('pa-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.voyageai.com/v1/embeddings'
        && $request->header('Authorization') === ['Bearer pa-test']);
})->skip(fn (): bool => ! in_array(EmbeddingProvider::Voyage, EmbeddingProvider::fitting(), true), 'Voyage AI does not return the stored vector size.');

it('stops on Finish when the embedding model returns another vector size', function (): void {
    setupFakeScaleway($this->dimensions + 8);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->call('complete')
        ->assertHasFormErrors(['embedding_model']);

    expect(savedSetting(PlatformSettings::class, 'setup_status'))->toBeNull()
        ->and(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});

it('stays on the step and scrolls to the reranker when its test question fails', function (): void {
    // Registered first, so it answers before the working fake below.
    Http::fake(['openrouter.ai/api/v1/rerank' => Http::response(['error' => ['message' => 'No endpoints found']], 404)]);
    setupFakeOpenRouter($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3)
        ->goToWizardStep(4)
        ->assertHasFormErrors(['openrouter_rerank_model'])
        ->assertDispatched('form-validation-error')
        ->assertWizardCurrentStep(3);
});

it('starts the reranker on OpenRouter with the key of the first step, from its live list', function (): void {
    setupFakeOpenRouter($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertWizardCurrentStep(3)
        ->assertFormSet(['reranker' => 'openrouter', 'openrouter_rerank_model' => 'cohere/rerank-4-pro'])
        ->assertFormFieldHidden('rerank_api_key')
        ->assertSee('Uses the OpenRouter key you entered in AI provider');

    // The strongest first (OpenRouter's live list adds the others it has).
    $models = array_keys($page->instance()->form->getFlatFields(withHidden: true)['openrouter_rerank_model']->getOptions());
    expect(array_slice($models, 0, 3))->toBe(['cohere/rerank-4-pro', 'voyageai/rerank-3', 'qwen/qwen3-reranker-8b']);

    $page->fillForm(['openrouter_rerank_model' => 'qwen/qwen3-reranker-8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'reranker'))->toBe('openrouter')
        ->and(savedSetting(AiSettings::class, 'openrouter_rerank_model'))->toBe('qwen/qwen3-reranker-8b');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/rerank'
        && $request->header('Authorization') === ['Bearer sk-or-test']
        && $request['model'] === 'qwen/qwen3-reranker-8b');
});

it('starts the reranker on "Not now" for another provider, and checks Cohere with its own key', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.cohere.com/v2/rerank' => Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9]]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['reranker' => 'none'])
        ->fillForm(['reranker' => 'cohere'])
        ->assertFormFieldVisible('rerank_api_key')
        ->fillForm(['rerank_api_key' => 'co-test', 'cohere_rerank_model' => 'rerank-v4.0-fast'])
        ->goToWizardStep(4)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(4)
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'reranker'))->toBe('cohere')
        ->and(savedSetting(AiSettings::class, 'cohere_rerank_model'))->toBe('rerank-v4.0-fast')
        ->and(savedSetting(AiProviderSettings::class, 'cohere_api_key'))->toBe('co-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/rerank'
        && $request->header('Authorization') === ['Bearer co-test']
        && $request['model'] === 'rerank-v4.0-fast');
});

it('reranks with the Cohere key typed for the embeddings', function (): void {
    setupFakeGroqAndCohere($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test'])
        ->goToWizardStep(3)
        ->fillForm(['embedding_provider' => 'cohere', 'embedding_api_key' => 'co-test', 'reranker' => 'cohere'])
        ->assertFormFieldHidden('rerank_api_key')
        ->assertSee('Uses the Cohere key above')
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'reranker'))->toBe('cohere');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/rerank'
        && $request->header('Authorization') === ['Bearer co-test']);
});

it('stops on the models step when the reranker refuses its key', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.cohere.com/v2/rerank' => Http::response(['message' => 'invalid api token'], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['reranker' => 'cohere', 'rerank_api_key' => 'wrong'])
        ->goToWizardStep(4)
        ->assertHasFormErrors(['rerank_api_key'])
        ->assertWizardCurrentStep(3);
});

it('reranks with a server of your own', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['reranker.example.com/v1/rerank' => Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9]]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['reranker' => 'custom'])
        ->assertFormFieldHidden('rerank_api_key')
        ->fillForm(['rerank_custom_url' => 'https://reranker.example.com/v1/', 'rerank_custom_model' => 'Qwen/Qwen3-Reranker-4B', 'rerank_custom_key' => 'own-key'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'reranker'))->toBe('custom')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_url'))->toBe('https://reranker.example.com/v1')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_model'))->toBe('Qwen/Qwen3-Reranker-4B')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_key'))->toBe('own-key');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://reranker.example.com/v1/rerank'
        && $request->header('Authorization') === ['Bearer own-key']);
});

it('adds a server of your own and takes a typed model name when it lists none', function (): void {
    Http::fake([
        'ollama.test:11434/*' => Http::response('Not found', 404),
        'api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, $this->dimensions, 0.1)]]]),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'custom', 'server_name' => 'Office Ollama', 'server_url' => 'http://ollama.test:11434/v1', 'embedding_provider' => 'cohere', 'embedding_api_key' => 'co-test'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertFormFieldVisible('chat_model_id')
        ->fillForm(['chat_model_id' => 'llama3.1:8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $server = LlmProvider::query()->where('kind', LlmProviderKind::Custom)->sole();

    expect($server->name)->toBe('Office Ollama')
        ->and($server->base_url)->toBe('http://ollama.test:11434/v1')
        ->and($server->api_key)->toBeNull()
        ->and($server->llms()->sole()->name)->toBe('llama3.1:8b');
});

it('keeps the embedding service once sources are trained', function (): void {
    Chunk::factory()->create(['chunk' => 'Trained text.']);
    setupFakeOpenRouter($this->dimensions);
    $provider = savedSetting(AiSettings::class, 'embedding_provider');

    setupPage($this->admin)
        ->assertSee('Embeddings are already set up')
        ->assertFormFieldHidden('embedding_provider')
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe($provider);
});

it('lets agents use several chat models, one of them the default', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['chat_models' => ['gpt-oss-120b', 'gemma-4-26b-a4b-it'], 'default_model' => 'gemma-4-26b-a4b-it'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $added = Llm::query()->whereIn('name', ['gpt-oss-120b', 'gemma-4-26b-a4b-it'])->where('is_enabled', true)->get();

    expect($added)->toHaveCount(2)
        ->and(Llm::query()->whereKey(savedSetting(AiSettings::class, 'default_agent_model'))->value('name'))->toBe('gemma-4-26b-a4b-it');
});

it('changes the chat models in a list with boxes to tick, keeping the default one of them', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['chat_models' => ['gemma-4-26b-a4b-it']])
        // The suggested model shows as a badge, with the button that changes it.
        ->assertSeeInOrder(['Models agents can use', 'gemma-4-26b-a4b-it', 'Manage models'])
        ->callAction('manageChatModels', data: ['models' => ['gpt-oss-120b', 'gemma-4-26b-a4b-it']])
        ->assertHasNoActionErrors()
        // The model chosen before keeps its place, and stays the default.
        ->assertFormSet(['chat_models' => ['gemma-4-26b-a4b-it', 'gpt-oss-120b'], 'default_model' => 'gemma-4-26b-a4b-it'])
        ->assertFormFieldVisible('default_model')
        ->callAction('manageChatModels', data: ['models' => ['gpt-oss-120b']])
        // The default was taken out: the model left takes its place.
        ->assertFormSet(['chat_models' => ['gpt-oss-120b'], 'default_model' => 'gpt-oss-120b'])
        ->assertFormFieldHidden('default_model')
        ->callAction('manageChatModels', data: ['models' => []])
        ->assertHasActionErrors(['models' => 'required']);
});

it('lists the chosen chat models first, then the suggested ones, then the rest', function (): void {
    Http::fake(['api.scaleway.ai/*/models' => Http::response(['data' => [
        ['id' => 'gpt-oss-120b'],
        ['id' => 'llama-3.3-70b-instruct'],
        ['id' => 'gemma-4-26b-a4b-it'],
    ]])]);
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['chat_models' => ['gemma-4-26b-a4b-it', 'llama-3.3-70b-instruct']])
        ->callAction('manageChatModels', data: ['models' => ['gpt-oss-120b', 'gemma-4-26b-a4b-it']])
        ->mountAction('manageChatModels')
        ->assertSchemaStateSet(['models' => ['gemma-4-26b-a4b-it', 'gpt-oss-120b']], 'mountedActionSchema0')
        ->assertMountedActionModalSeeHtml(['cv-model-picker__count', ' of 3 models picked']);

    $list = $page->instance()->getSchema('mountedActionSchema0')->getFlatFields()['models'];

    // One column, row by row: CSS columns scroll sideways in a list of fixed height.
    expect($list->getGridDirection())->toBe(GridDirection::Row)
        ->and(array_keys($list->getOptions()))->toBe(['gemma-4-26b-a4b-it', 'gpt-oss-120b', 'llama-3.3-70b-instruct'])
        ->and($list->getDescription('llama-3.3-70b-instruct'))->toBe('Suggested: calls tools well')
        ->and($list->hasDescription('gpt-oss-120b'))->toBeFalse();
});

it('warns when a chosen chat model cannot call tools, and still keeps it', function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['scaleway' => ['models' => [
        'gpt-oss-120b' => ['tool_call' => false, 'canonical_model_id' => 'openai/gpt-oss-120b'],
        'gemma-4-26b-a4b-it' => ['tool_call' => true],
    ]]], 'models' => []])]);
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertSet('modelsWithoutTools', ['gpt-oss-120b'])
        ->callAction('manageChatModels', data: ['models' => ['gemma-4-26b-a4b-it']])
        ->assertDontSee('cannot call tools')
        ->callAction('manageChatModels', data: ['models' => ['gemma-4-26b-a4b-it', 'gpt-oss-120b']])
        ->assertFormSet(['chat_models' => ['gemma-4-26b-a4b-it', 'gpt-oss-120b']])
        ->assertSee(['gpt-oss-120b cannot call tools', 'Tools will not work for agents that use it', 'No tool calling: tools will not work']);

    $list = $page->mountAction('manageChatModels')->instance()->getSchema('mountedActionSchema0')->getFlatFields()['models'];

    expect($list->getDescription('gpt-oss-120b'))->toBe('No tool calling: tools will not work')
        // Each model with its lab's logo, else its provider's.
        ->and($list->getOptions()['gpt-oss-120b'])->toContain('/admin/model-logos/labs/openai.svg')
        ->and($list->getOptions()['gemma-4-26b-a4b-it'])->toContain('/admin/model-logos/scaleway.svg');
});

it('lists only the models that read images for image descriptions', function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['scaleway' => ['models' => [
        'gpt-oss-120b' => ['modalities' => ['input' => ['text'], 'output' => ['text']]],
        'gemma-4-26b-a4b-it' => ['modalities' => ['input' => ['text', 'image'], 'output' => ['text']]],
    ]]], 'models' => []])]);
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['describe_images' => true]);

    $field = $page->instance()->form->getFlatFields(withHidden: true)['vision_model'];

    expect(array_keys($field->getOptions()))->toBe(['gemma-4-26b-a4b-it']);
});

it('lists only the models that read PDF files and images for chat file uploads, off without a recommended one', function (): void {
    forgetModelsDevCatalog();
    Http::fake([ModelsDevCatalog::URL => Http::response(['providers' => ['scaleway' => ['models' => [
        'gpt-oss-120b' => ['modalities' => ['input' => ['text', 'image'], 'output' => ['text']]],
        'gemma-4-26b-a4b-it' => ['modalities' => ['input' => ['text', 'image', 'pdf'], 'output' => ['text']]],
    ]]], 'models' => []])]);
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        // Scaleway has no recommended model that reads PDF files.
        ->assertFormSet(['chat_files' => false, 'files_model' => null])
        ->fillForm(['chat_files' => true]);

    $field = $page->instance()->form->getFlatFields(withHidden: true)['files_model'];

    expect(array_keys($field->getOptions()))->toBe(['gemma-4-26b-a4b-it']);
});

it('tests each chosen chat model as the test window opens: it answers, is busy, or says why not', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.scaleway.ai/v1/chat/completions' => fn (Request $request) => match ($request['model']) {
        'gpt-oss-120b' => Http::response(['error' => ['message' => 'Model not found']], 404),
        'llama-3.3-70b-instruct' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
        default => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'OK'], 'finish_reason' => 'stop']]]),
    }]);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->callAction('manageChatModels', data: ['models' => ['gemma-4-26b-a4b-it', 'gpt-oss-120b', 'llama-3.3-70b-instruct']])
        ->mountAction(TestAction::make('testChat')->schemaComponent('chat', schema: 'form'))
        ->assertActionMounted(TestAction::make('testChat')->schemaComponent('chat', schema: 'form'));

    $page->call('testModel', 'chat', 'gemma-4-26b-a4b-it')->assertReturned(['state' => 'passed', 'message' => null]);
    $page->call('testModel', 'chat', 'gpt-oss-120b')->assertReturned(['state' => 'failed', 'message' => 'The model did not answer (error 404): Model not found']);
    $page->call('testModel', 'chat', 'llama-3.3-70b-instruct')->assertReturned([
        'state' => 'busy',
        'message' => 'Busy right now (error 429): the provider is limiting requests. Test again in a minute. Rate limit exceeded',
    ]);
    // Only the chosen models are asked.
    $page->call('testModel', 'chat', 'made-up-model')->assertReturned(['state' => 'failed', 'message' => 'This is not one of the models to test.']);

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/chat/completions') && $request->hasHeader('Authorization', 'Bearer scw-test'));
});

it('lists what it tests in the test window, each waiting for its turn', function (): void {
    $html = view('filament.setup.model-tests', ['section' => 'chat', 'image' => null, 'rows' => [
        ['key' => 'qwen/qwen3', 'label' => '<b>Qwen3</b>'],
        ['key' => 'gpt-oss-120b', 'label' => 'gpt-oss-120b'],
    ]])->render();

    expect($html)->toContain('<b>Qwen3</b>', 'gpt-oss-120b', '$wire.testModel(\'chat\', key)', 'cv-model-test__spinner');
});

it('tests the embedding model with the key typed', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['embedding_provider' => 'qwen'])
        ->assertActionVisible(TestAction::make('testEmbeddings')->schemaComponent('embeddings', schema: 'form'))
        ->call('testModel', 'embeddings', 'embeddings')
        ->assertReturned(['state' => 'passed', 'message' => null]);
});

it('shows the model for image descriptions a cat, and gives its description', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.scaleway.ai/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => "A cartoon orange cat\n smiling."], 'finish_reason' => 'stop']]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['describe_images' => true, 'vision_model' => 'gemma-4-26b-a4b-it'])
        ->call('testModel', 'images', 'gemma-4-26b-a4b-it')
        ->assertReturned(['state' => 'passed', 'message' => 'A cartoon orange cat smiling.']);

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/chat/completions')
        && Str::startsWith((string) data_get($request->data(), 'messages.0.content.1.image_url.url'), 'data:image/png;base64,'));
});

it('sends the model for chat file uploads a one-page PDF file, and gives its summary', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.scaleway.ai/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => "The bakery's hours,\n orders and delivery."], 'finish_reason' => 'stop']]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['chat_files' => true, 'files_model' => 'gemma-4-26b-a4b-it'])
        ->assertFormComponentActionVisible('files_model', 'testFiles')
        ->call('testModel', 'files', 'gemma-4-26b-a4b-it')
        ->assertReturned(['state' => 'passed', 'message' => "The bakery's hours, orders and delivery."]);

    Http::assertSent(fn (Request $request): bool => Str::endsWith($request->url(), '/chat/completions')
        && data_get($request->data(), 'messages.0.content.1.file.filename') === 'chat-file.pdf'
        && Str::startsWith((string) data_get($request->data(), 'messages.0.content.1.file.file_data'), 'data:application/pdf;base64,'));
});

it('names each model in the list, with its id under the name', function (): void {
    setupFakeOpenRouter($this->dimensions);

    $list = setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToWizardStep(3)
        ->assertSee('Llama 4 Maverick')
        ->mountAction('manageChatModels')
        ->instance()
        ->getSchema('mountedActionSchema0')
        ->getFlatFields()['models'];

    expect(array_map(fn (string $label): string => Str::of($label)->stripTags()->toString(), $list->getOptions()))->toBe(['meta-llama/llama-4-maverick' => 'Llama 4 Maverick'])
        ->and($list->getDescription('meta-llama/llama-4-maverick'))->toBe('meta-llama/llama-4-maverick · Suggested: calls tools well');
});

it('takes only chat models from the provider\'s list', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['chat_models' => ['gpt-oss-120b', 'made-up-model']])
        ->call('complete')
        ->assertHasFormErrors(['chat_models']);

    expect(Llm::query()->where('name', 'made-up-model')->exists())->toBeFalse();
});

it('reads documents with Azure, with its key and endpoint checked', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->goToWizardStep(4)
        ->assertHasNoFormErrors()
        ->assertFormSet(['document_reader' => 'none'])
        ->assertSee('People cannot upload PDF, Word, slides or images.')
        ->fillForm(['document_reader' => 'azure', 'document_api_key' => 'az-key', 'document_endpoint' => 'https://docs.cognitiveservices.azure.com/'])
        ->goToWizardStep(5)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiProviderSettings::class, 'azure_api_key'))->toBe('az-key')
        ->and(savedSetting(AiProviderSettings::class, 'azure_endpoint'))->toBe('https://docs.cognitiveservices.azure.com')
        ->and(savedSetting(AiSettings::class, 'document_parser'))->toBe('azure');
});

it('warns before going on without a document reader, and goes on once agreed', function (): void {
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->goToWizardStep(4)
        ->assertFormSet(['document_reader' => 'none'])
        ->goToWizardStep(5)
        ->assertActionMounted('noReader')
        ->assertMountedActionModalSee(['Continue without a document reader?', 'PDF, Word, slides and images cannot be read', 'the app reads them itself', 'Continue without one', 'Choose a reader'])
        ->assertWizardCurrentStep(4, 'form')
        ->callMountedAction()
        ->assertWizardCurrentStep(5)
        ->assertSet('noReaderConfirmed', true);

    // Agreed once: Next does not ask again, until the choice changes.
    $page->goToWizardStep(5)->assertActionNotMounted('noReader')->assertWizardCurrentStep(5);
    $page->fillForm(['document_reader' => 'azure'])->fillForm(['document_reader' => 'none'])->assertSet('noReaderConfirmed', false);
});

it('stops on the ingestion step when Azure refuses the key', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '401']], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'document_reader' => 'azure', 'document_api_key' => 'wrong', 'document_endpoint' => 'https://docs.cognitiveservices.azure.com'])
        ->goToWizardStep(5)
        ->assertHasFormErrors(['document_api_key']);
});

it('reads documents with the Mistral key of the first step', function (): void {
    Http::fake([
        'api.mistral.ai/v1/models' => Http::response(['data' => [['id' => 'mistral-medium-latest', 'capabilities' => ['completion_chat' => true, 'function_calling' => true]]]]),
        'api.mistral.ai/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, 1024, 0.1)]]]),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'mistral', 'api_key' => 'mistral-test'])
        ->assertFormSet(['document_reader' => 'mistral'])
        ->goToWizardStep(3)
        ->goToWizardStep(4)
        ->assertSee('Uses the Mistral key you entered in AI provider')
        ->assertFormFieldHidden('document_api_key')
        ->goToWizardStep(5)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiProviderSettings::class, 'mistral_api_key'))->toBe('mistral-test')
        ->and(savedSetting(AiSettings::class, 'document_parser'))->toBe('mistral');
})->skip(fn (): bool => app(EmbeddingStorage::class)->dimensions() !== 1024, 'Mistral embeddings are 1024 dimensions only.');

it('starts every optional service on "Not now" and storage on this server, whatever .env suggests', function (): void {
    config(['filesystems.storage' => 's3', 'mail.default' => 'smtp', 'chatvia.ai.transcriber' => 'auto']);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway'])
        ->assertFormSet(['reranker' => 'none', 'document_reader' => 'none', 'website_import' => 'none', 'transcriber' => 'none', 'voice' => 'none', 'storage_driver' => 'local', 'mailer' => 'log'])
        ->assertSee(['Documents and websites', 'Speech to text and live calls', 'Mailer', 'A log of every AI call'])
        ->assertDontSee('Optional:');
});

it('turns the services left at "Not now" off, so Automatic does not turn them on', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'transcriber'))->toBe('none')
        ->and(savedSetting(AiSettings::class, 'voice'))->toBe('none')
        ->and(savedSetting(AiSettings::class, 'document_parser'))->toBe('none')
        ->and(savedSetting(AiSettings::class, 'reranker'))->toBe('none')
        ->and(savedSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBeNull();
});

it('imports websites with Firecrawl, with its key checked', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.firecrawl.dev/v2/team/credit-usage' => Http::response(['success' => true, 'data' => ['remainingCredits' => 500]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->goToWizardStep(4)
        ->assertSee('To learn from a website, agents need Firecrawl.')
        ->assertFormFieldHidden('firecrawl_api_key')
        ->fillForm(['website_import' => 'firecrawl'])
        ->goToWizardStep(5)
        ->assertHasFormErrors(['firecrawl_api_key' => 'required'])
        ->fillForm(['firecrawl_api_key' => 'fc-test'])
        ->goToWizardStep(5)
        // The key works; the step still asks about the missing document reader.
        ->assertActionMounted('noReader')
        ->callMountedAction()
        ->assertWizardCurrentStep(5)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBe('fc-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.firecrawl.dev/v2/team/credit-usage'
        && $request->header('Authorization') === ['Bearer fc-test']);
});

it('stops on the ingestion step when Firecrawl refuses the key', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.firecrawl.dev/*' => Http::response(['success' => false, 'error' => 'Unauthorized: Invalid token'], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'website_import' => 'firecrawl', 'firecrawl_api_key' => 'wrong'])
        ->goToWizardStep(5)
        ->assertHasFormErrors(['firecrawl_api_key'])
        ->assertActionNotMounted('noReader');
});

it('turns speech to text on with the key of the first step', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->set('noReaderConfirmed', true)
        ->goToWizardStep(5)
        ->assertSee('Agents can then learn from audio and video files and search what is said in them')
        ->fillForm(['transcriber' => 'scaleway'])
        ->assertSee('Uses the Scaleway AI key you entered in AI provider')
        ->assertFormFieldHidden('transcription_api_key')
        ->goToWizardStep(6)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'transcriber'))->toBe('scaleway');
});

it('asks for the key of a speech service on another account, and checks it', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'whisper-1']]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'transcriber' => 'openai'])
        ->assertFormFieldVisible('transcription_api_key')
        ->goToWizardStep(3)
        ->goToWizardStep(6)
        ->assertHasFormErrors(['transcription_api_key' => 'required'])
        ->fillForm(['transcription_api_key' => 'sk-openai'])
        ->goToWizardStep(6)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'transcriber'))->toBe('openai')
        ->and(savedSetting(AiProviderSettings::class, 'openai_api_key'))->toBe('sk-openai');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->header('Authorization') === ['Bearer sk-openai']);
});

it('asks for each key once: a later step uses the key typed in an earlier one', function (): void {
    setupPage($this->admin)
        // Groq makes no embeddings: OpenAI makes them, with a key typed in the Models step.
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test', 'embedding_provider' => 'openai', 'embedding_api_key' => 'sk-openai', 'transcriber' => 'openai'])
        ->assertFormFieldVisible('embedding_api_key')
        ->assertFormFieldHidden('transcription_api_key')
        ->assertSee('Uses the OpenAI key you entered in Models');
});

it('uses a saved key without asking for it again', function (): void {
    config(['services.openai.api_key' => 'sk-saved', 'services.cohere.api_key' => 'co-saved']);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'embedding_provider' => 'cohere', 'transcriber' => 'openai'])
        ->assertFormFieldHidden('embedding_api_key')
        ->assertFormFieldHidden('transcription_api_key')
        ->assertSee(['Uses your saved Cohere key', 'Uses your saved OpenAI key', 'You can change it in Settings']);
});

it('stops on the voice step when the speech service refuses its key', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'transcriber' => 'openai', 'transcription_api_key' => 'wrong'])
        ->goToWizardStep(6)
        ->assertHasFormErrors(['transcription_api_key']);
});

it('turns voice conversations on with the OpenAI key of the first step, and explains what they need', function (): void {
    Http::fake([
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-6-luna'], ['id' => 'gpt-6.1-sol']]]),
        'api.openai.com/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $this->dimensions, 0.1)]]]),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openai', 'api_key' => 'sk-openai'])
        ->goToWizardStep(3)
        ->set('noReaderConfirmed', true)
        ->goToWizardStep(5)
        ->assertSee(['This uses the OpenAI Realtime API, so it needs an OpenAI key', 'Each agent still turns calls on in its own setup'])
        ->assertFormSet(['voice' => 'none'])
        ->fillForm(['voice' => 'openai'])
        ->assertSee('Uses the OpenAI key you entered in AI provider')
        ->assertFormFieldHidden('voice_api_key')
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(AiSettings::class, 'voice'))->toBe('openai');
});

it('asks for an OpenAI key for voice conversations on another provider, and checks it', function (): void {
    setupFakeScaleway($this->dimensions);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'voice' => 'openai'])
        ->assertFormFieldVisible('voice_api_key')
        ->goToWizardStep(3)
        ->goToWizardStep(6)
        ->assertHasFormErrors(['voice_api_key' => 'required'])
        ->fillForm(['voice_api_key' => 'wrong'])
        ->goToWizardStep(6)
        ->assertHasFormErrors(['voice_api_key'])
        ->assertDispatched('form-validation-error');
});

it('asks for an OpenAI key once when speech to text and voice conversations both use it', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'transcriber' => 'openai', 'voice' => 'openai'])
        ->assertFormFieldVisible('transcription_api_key')
        ->assertFormFieldHidden('voice_api_key')
        ->assertSee('Uses the OpenAI key above')
        ->goToWizardStep(3)
        ->goToWizardStep(6)
        ->assertHasFormErrors(['transcription_api_key' => 'required']);
});

it('records AI calls with Spectra by default, and says what Spectra is', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->assertFormSet(['observability' => 'spectra'])
        ->assertSee(['Spectra keeps a log of every call ChatVia.ai makes to an AI service', 'Record AI calls with Spectra', 'Settings → Observability'])
        ->assertDontSee('Spectra is left out of this install')
        ->goToWizardStep(3)
        ->call('complete')
        ->assertHasNoFormErrors();

    // On is the default: it stays unsaved, so the default keeps applying.
    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeNull();
});

it('turns Spectra off when the admin chooses "Not now"', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'observability' => 'none'])
        ->goToWizardStep(3)
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeFalse();
});

it('starts on "Not now" for Spectra once an admin turned it off in Settings', function (): void {
    saveSettings(ObservabilitySettings::class, ['spectra_enabled' => false]);

    setupPage($this->admin)->assertFormSet(['observability' => 'none']);
});

it('keeps the storage and the mailer an admin saved in Settings', function (): void {
    saveSettings(StorageSettings::class, ['driver' => 's3']);
    saveSettings(MailSettings::class, ['mailer' => 'smtp']);

    setupPage($this->admin)->assertFormSet(['storage_driver' => 's3', 'mailer' => 'smtp']);
});

it('asks for S3 details, and stops when the storage does not work', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'storage_driver' => 's3'])
        ->goToWizardStep(7)
        ->assertHasFormErrors(['s3_region', 's3_key', 's3_secret', 's3_bucket'])
        ->fillForm([
            's3_endpoint' => 'http://127.0.0.1:9',
            's3_region' => 'us-east-1',
            's3_key' => 'key',
            's3_secret' => 'secret',
            's3_bucket' => 'files',
        ])
        ->goToWizardStep(7)
        ->assertHasFormErrors(['s3_bucket']);
});

it('sends email with the provider set up, or warns that nobody gets email', function (): void {
    setupFakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->assertFormSet(['mailer' => 'log'])
        ->assertSee('Without email, nobody receives emails')
        ->fillForm(['mailer' => 'smtp', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_password' => 'mail-secret', 'from_address' => 'support@acme.test'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(MailSettings::class, 'mailer'))->toBe('smtp')
        ->and(savedSetting(MailSettings::class, 'smtp_host'))->toBe('smtp.example.com')
        ->and(savedSetting(MailSettings::class, 'smtp_password'))->toBe('mail-secret')
        ->and(savedSetting(MailSettings::class, 'from_address'))->toBe('support@acme.test');
});

it('ends on a final step with the choices made, and where the defaults are changed', function (): void {
    setupFakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(3)
        ->fillForm(['chat_models' => ['gpt-oss-120b', 'gemma-4-26b-a4b-it'], 'default_model' => 'gemma-4-26b-a4b-it'])
        ->goToWizardStep(9)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(9)
        ->assertSeeInOrder([
            'Complete', 'Check your choices, then press',
            'Your choices',
            'AI provider', 'Scaleway AI',
            'Chat models', 'gpt-oss-120b, gemma-4-26b-a4b-it (default)',
            'Model for quick tasks', 'gemma-4-26b-a4b-it',
            'Image descriptions', 'gemma-4-26b-a4b-it',
            'Embeddings', 'Qwen3 on Scaleway AI', config('services.scalewayai.embedding_model'),
            'Reranker', 'Not now',
            'Document reader', 'Not now',
            'Website import', 'Not now',
            'Speech to text', 'Not now',
            'Voice conversations', 'Not now',
            'Storage', 'This server',
            'Mailer', 'Not now',
            'Observability', 'Spectra',
            'Default values',
            'Knowledge', 'How texts are split into chunks',
            'AI models', 'More chat models, and a model for each app task',
        ])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(savedSetting(PlatformSettings::class, 'setup_status'))->toBe('completed');
});
