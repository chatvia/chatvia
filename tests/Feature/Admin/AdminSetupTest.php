<?php

use App\Admin\Pages\Setup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SearchSettings;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * The admin setup of a new install: every panel page leads to it until it is
 * done or skipped. Search comes first: hybrid search and the embedding
 * service, checked with a test embedding against the vector size the
 * database stores. Then the AI provider and model, whose list both fills the
 * model picker and checks the key. It all saves in one go
 * ({@see CompleteSetupAction}).
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->dimensions = app(EmbeddingStorage::class)->dimensions();

    Filament::setCurrentPanel('admin');
    setupNotDone();
});

function setupNotDone(): void
{
    $platform = app(PlatformSettings::class);
    $platform->setup_completed_at = null;
    $platform->save();
}

function setupSetting(string $class, string $property): mixed
{
    app()->forgetScopedInstances();

    return app($class)->{$property};
}

function setupPage(User $admin): Testable
{
    return Livewire::actingAs($admin, 'admin')->test(Setup::class);
}

/** @return array<string, string> The search step, filled in for Cohere. */
function cohereSearch(string $model = 'embed-v4.0'): array
{
    return ['embedding_provider' => 'cohere', 'embedding_model' => $model, 'embedding_api_key' => 'co-test'];
}

/** Cohere and Scaleway embed into vectors of this size. */
function fakeEmbeddings(int $dimensions): void
{
    Http::fake([
        'api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, $dimensions, 0.1)]]]),
        'api.scaleway.ai/*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $dimensions, 0.1)]]]),
    ]);
}

function fakeOpenRouterModels(): void
{
    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
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
    ]])]);
}

it('leads every panel page to the setup until it is done', function (): void {
    $this->actingAs($this->admin, 'admin');

    $this->get(Filament::getUrl())->assertRedirect(route('filament.admin.setup'));
    $this->get(route('filament.admin.auth.profile'))->assertOk();
    $this->get(route('filament.admin.setup'))->assertOk()->assertSee('Welcome to');

    $platform = app(PlatformSettings::class);
    $platform->setup_completed_at = now()->toIso8601String();
    $platform->save();

    $this->get(Filament::getUrl())->assertOk();
});

it('starts with search: hybrid on and the vector size read from the database', function (): void {
    setupPage($this->admin)
        ->assertWizardCurrentStep(1)
        ->assertSee("Your database stores {$this->dimensions}-dimension vectors")
        ->assertSee(app(EmbeddingStorage::class)->columnType())
        ->assertDontSee('Send email with')
        ->assertFormSet([
            'hybrid_search' => true,
            'embedding_provider' => 'qwen',
            'embedding_model' => config('services.scalewayai.embedding_model'),
            // The chat provider of the same service, so one key does both.
            'chat_kind' => 'scaleway',
        ]);
});

it('explains hybrid search in a popup', function (): void {
    setupPage($this->admin)
        ->assertDontSee('llmlingua2')
        ->mountAction(TestAction::make('hybridSearchHelp')->schemaComponent('hybrid-search', schema: 'form'))
        ->assertMountedActionModalSee(['What is hybrid search?', 'By meaning', 'By keywords (BM25)', 'VectorChord BM25', 'llmlingua2']);
});

it('checks the search service with a test embedding before the next step', function (): void {
    fakeEmbeddings($this->dimensions);
    $key = config('services.cohere.api_key');

    setupPage($this->admin)
        ->fillForm(cohereSearch('embed-v4.0'))
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/embed'
        && $request->header('Authorization') === ['Bearer co-test']
        && $request['model'] === 'embed-v4.0'
        && $request['output_dimension'] === $this->dimensions);

    // The typed key is only used for the check until the setup is saved.
    expect(config('services.cohere.api_key'))->toBe($key);
});

it('stops on the search step when the model returns another vector size', function (): void {
    fakeEmbeddings(768);

    setupPage($this->admin)
        ->fillForm(cohereSearch())
        ->goToNextWizardStep()
        ->assertHasErrors(['data.embedding_model'])
        ->assertWizardCurrentStep(1)
        ->assertSee("returned 768 dimensions, but the database stores {$this->dimensions}");
});

it('stops on the search step when the service refuses the key', function (): void {
    Http::fake(['api.cohere.com/*' => Http::response(['message' => 'invalid api token'], 401)]);

    setupPage($this->admin)
        ->fillForm(cohereSearch())
        ->goToNextWizardStep()
        ->assertHasErrors(['data.embedding_api_key'])
        ->assertWizardCurrentStep(1)
        ->assertSee('Cohere refused the API key');
});

it('lists the provider\'s chat models once the key is typed', function (): void {
    fakeOpenRouterModels();

    setupPage($this->admin)
        ->fillForm(['chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->assertSet('models', ['meta-llama/llama-4-maverick' => 'Llama 4 Maverick (meta-llama/llama-4-maverick)'])
        ->assertFormFieldVisible('chat_model')
        ->assertFormFieldHidden('chat_model_id');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/models'
        && $request->header('Authorization') === ['Bearer sk-or-test']);
});

it('saves search and the chat model, then opens the panel', function (): void {
    fakeOpenRouterModels();

    setupPage($this->admin)
        ->fillForm(['hybrid_search' => false, ...cohereSearch('embed-multilingual-v3.0'), 'chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->fillForm(['chat_model' => 'meta-llama/llama-4-maverick'])
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $provider = LlmProvider::query()->where('kind', LlmProviderEnum::OPENROUTER)->sole();
    $model = Llm::query()->where('llm_provider_id', $provider->id)->sole();

    expect($provider->api_key)->toBe('sk-or-test')
        ->and($model->internal_name)->toBe('meta-llama/llama-4-maverick')
        ->and($model->descriptive_name)->toBe('Llama 4 Maverick')
        ->and($model->context_window)->toBe(1_048_576)
        ->and((float) $model->input_price_per_1m_tokens)->toBe(0.15)
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and(setupSetting(AiSettings::class, 'default_agent_model'))->toBe($model->external_name)
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere')
        ->and(setupSetting(AiSettings::class, 'cohere_embedding_model'))->toBe('embed-multilingual-v3.0')
        ->and(setupSetting(AiSettings::class, 'cohere_api_key'))->toBe('co-test')
        ->and(setupSetting(SearchSettings::class, 'hybrid_search'))->toBeFalse()
        ->and(setupSetting(PlatformSettings::class, 'setup_completed_at'))->not->toBeNull();
});

it('leaves defaults unsaved: hybrid search on and the service\'s own model', function (): void {
    fakeOpenRouterModels();

    setupPage($this->admin)
        ->fillForm([...cohereSearch((string) config('services.cohere.embedding_model')), 'chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->fillForm(['chat_model' => 'meta-llama/llama-4-maverick'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(SearchSettings::class, 'hybrid_search'))->toBeNull()
        ->and(setupSetting(AiSettings::class, 'cohere_embedding_model'))->toBeNull()
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere');
});

it('stops on Finish when the provider refuses the key', function (): void {
    Http::fake(['openrouter.ai/*' => Http::response(['error' => ['message' => 'No auth credentials found']], 401)]);

    setupPage($this->admin)
        ->fillForm([...cohereSearch(), 'chat_kind' => 'openrouter', 'chat_api_key' => 'wrong-key', 'chat_model_id' => 'some/model'])
        ->call('complete')
        ->assertHasErrors(['data.chat_api_key'])
        ->assertSee('refused the API key');

    expect(LlmProvider::query()->where('kind', LlmProviderEnum::OPENROUTER)->exists())->toBeFalse()
        ->and(setupSetting(PlatformSettings::class, 'setup_completed_at'))->toBeNull();
});

it('takes a typed model name from a server that lists none', function (): void {
    Http::fake(['ollama.test/*' => Http::response('Not found', 404)]);

    setupPage($this->admin)
        ->fillForm([...cohereSearch(), 'chat_kind' => 'custom', 'chat_base_url' => 'http://ollama.test:11434/v1'])
        ->call('loadModels')
        ->assertFormFieldVisible('chat_model_id')
        ->fillForm(['chat_model_id' => 'llama3.1:8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $provider = LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->sole();

    expect(app(ProviderAdapters::class)->for(LlmProviderEnum::CUSTOM)->baseUrl($provider))->toBe('http://ollama.test:11434/v1')
        ->and($provider->api_key)->toBeNull()
        ->and($provider->llms()->sole()->internal_name)->toBe('llama3.1:8b');
});

it('reuses the Scaleway search key for Scaleway chat models', function (): void {
    fakeEmbeddings($this->dimensions);
    Http::fake(['api.scaleway.ai/*/models' => Http::response(['data' => [['id' => 'qwen3-235b-a22b-instruct-2507']]])]);

    setupPage($this->admin)
        ->fillForm(['embedding_provider' => 'qwen', 'embedding_model' => 'qwen3-embedding-8b', 'embedding_api_key' => 'scw-test'])
        ->assertFormSet(['chat_kind' => 'scaleway'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2)
        // Next loaded the models with the search key.
        ->assertFormFieldVisible('chat_model')
        ->assertFormFieldHidden('chat_api_key')
        ->assertSee('key from the Search step')
        ->fillForm(['chat_model' => 'qwen3-235b-a22b-instruct-2507'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $model = Llm::query()->where('external_name', setupSetting(AiSettings::class, 'default_agent_model'))->sole();

    expect($model->provider->kind)->toBe(LlmProviderEnum::SCALEWAY)
        ->and($model->provider->api_key)->toBe('scw-test')
        ->and(setupSetting(AiSettings::class, 'scaleway_api_key'))->toBe('scw-test')
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('qwen');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/models')
        && $request->header('Authorization') === ['Bearer scw-test']);
});

it('offers OpenAI and Google Gemini search, and an OpenAI key also runs OpenAI chat', function (): void {
    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $this->dimensions, 0.1)]]]),
        'api.openai.com/v1/models' => Http::response(['data' => [['id' => 'gpt-5-mini']]]),
    ]);

    setupPage($this->admin)
        ->assertSee(['OpenAI', 'Google Gemini'])
        ->fillForm(['embedding_provider' => 'openai'])
        ->assertFormSet(['embedding_model' => 'text-embedding-3-small', 'chat_kind' => 'openai'])
        ->fillForm(['embedding_api_key' => 'sk-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2)
        ->assertFormFieldHidden('chat_api_key')
        ->fillForm(['chat_model' => 'gpt-5-mini'])
        ->call('complete')
        ->assertHasNoFormErrors();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/embeddings'
        && $request['model'] === 'text-embedding-3-small'
        && $request['dimensions'] === $this->dimensions);

    expect(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('openai')
        ->and(setupSetting(AiSettings::class, 'openai_api_key'))->toBe('sk-test');
});

it('keeps a Scaleway project address, for chat and for Scaleway search', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['data' => [['id' => 'mistral-small-3.2-24b-instruct-2506']]])]);

    $page = setupPage($this->admin)
        ->fillForm([...cohereSearch(), 'chat_kind' => 'scaleway', 'chat_base_url' => 'https://api.scaleway.ai/project-x/v1', 'chat_api_key' => 'scw-test'])
        ->assertFormFieldVisible('chat_base_url')
        ->fillForm(['chat_model' => 'mistral-small-3.2-24b-instruct-2506']);

    $requests = count(Http::recorded());

    // Finish checks the key with the list already loaded for what is typed.
    $page->call('complete')->assertHasNoFormErrors();

    expect(count(Http::recorded()))->toBe($requests);

    $provider = LlmProvider::query()->where('base_url', 'https://api.scaleway.ai/project-x/v1')->sole();
    $model = $provider->llms()->sole();

    expect($provider->base_url)->toBe('https://api.scaleway.ai/project-x/v1')
        ->and($model->context_window)->toBe(128_000)
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and(setupSetting(AiSettings::class, 'scaleway_endpoint'))->toBe('https://api.scaleway.ai/project-x/v1')
        ->and(setupSetting(AiSettings::class, 'scaleway_api_key'))->toBe('scw-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.scaleway.ai/project-x/v1/models');
});

it('keeps the search service once sources are trained, but hybrid search can change', function (): void {
    $file = AgentFileFactory::new()->create();
    Chunk::query()->create([
        'workspace_id' => $file->workspace_id,
        'chunkable_type' => $file->getMorphClass(),
        'chunkable_id' => $file->id,
        'chunk_index' => 1,
        'chunk' => 'Trained text.',
        'tokens' => 2,
        'embedding' => '['.implode(',', array_fill(0, $this->dimensions, 0.1)).']',
        'embedding_bm25' => Bm25Tokenizer::expression('Trained text.'),
        'checksum' => md5('Trained text.'),
        'cost_in_micro_cents' => 0,
    ]);
    fakeOpenRouterModels();
    $provider = setupSetting(AiSettings::class, 'embedding_provider');

    setupPage($this->admin)
        ->assertSee('Search is already set up')
        ->assertFormFieldDoesNotExist('embedding_provider')
        ->fillForm(['hybrid_search' => false, 'chat_kind' => 'openrouter', 'chat_api_key' => 'sk-or-test'])
        ->call('loadModels')
        ->fillForm(['chat_model' => 'meta-llama/llama-4-maverick'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'embedding_provider'))->toBe($provider)
        ->and(setupSetting(SearchSettings::class, 'hybrid_search'))->toBeFalse();
});

it('can be skipped', function (): void {
    setupPage($this->admin)
        ->callAction('skip')
        ->assertRedirect(Filament::getUrl());

    expect(setupSetting(PlatformSettings::class, 'setup_completed_at'))->not->toBeNull();
});

it('does not ask for two-factor authentication', function (): void {
    setupPage($this->admin)
        ->assertDontSee('Set up two-factor authentication first')
        ->assertSee('Finish setup');
});
