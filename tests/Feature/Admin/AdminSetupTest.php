<?php

use App\Admin\Clusters\Settings\Pages\SettingsOverview;
use App\Admin\Pages\Setup;
use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SetupStatus;
use App\Admin\Settings\StorageSettings;
use App\Identity\Workspace\Models\User;
use Database\Factories\AgentFileFactory;
use Domain\Integration\Mcp\Jobs\SyncBuiltinConnectorsJob;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Services\Bm25Tokenizer;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Support\Http\SafeUrl;

/**
 * The admin setup of a new install: every panel page leads to it until it is
 * done or skipped. "Connect AI": a provider and its key, checked by reading
 * its model list; a provider that makes no embeddings of the stored size
 * also needs an embedding service and its key. "Models": the chat model, and
 * the embedding model (OpenRouter's from its list) checked with a test
 * embedding. Each key is saved once, in
 * Settings → AI providers ({@see CompleteSetupAction}).
 */
beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create()->fresh();
    $this->dimensions = app(EmbeddingStorage::class)->dimensions();

    Filament::setCurrentPanel('admin');
    Queue::fake([SyncBuiltinConnectorsJob::class]);
    setupNotDone();

    // A new install: no key is saved yet.
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

function setupNotDone(): void
{
    $platform = app(PlatformSettings::class);
    $platform->setup_status = null;
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

/** The Scaleway model list, and its embeddings of a given size. */
function fakeScaleway(int $dimensions): void
{
    Http::fake([
        'api.scaleway.ai/*/models' => Http::response(['data' => [['id' => 'gpt-oss-120b'], ['id' => 'mistral-small-3.2-24b-instruct-2506']]]),
        'api.scaleway.ai/*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $dimensions, 0.1)]]]),
    ]);
}

/** The OpenRouter model lists (one chat model, one that cannot chat; two embedding models) and its embeddings. */
function fakeOpenRouter(int $dimensions): void
{
    Http::fake([
        'openrouter.ai/api/v1/embeddings/models' => Http::response(['data' => [
            ['id' => 'openai/text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 Small'],
            ['id' => 'baai/bge-m3', 'name' => 'BAAI: bge-m3'],
            ['id' => 'openai/text-embedding-3-small:batch', 'name' => 'OpenAI: Text Embedding 3 Small (batch)'],
        ]]),
        'openrouter.ai/api/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, $dimensions, 0.1)]]]),
        'openrouter.ai/api/v1/models' => Http::response(['data' => [
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

/** The Groq model list, and Cohere embeddings. */
function fakeGroqAndCohere(int $dimensions): void
{
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'openai/gpt-oss-120b']]]),
        'api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, $dimensions, 0.1)]]]),
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
        ->assertMountedActionModalSee(['Chatvia does not work without these', 'an AI provider for chat', 'an embedding service', 'agents cannot answer'])
        ->callMountedAction()
        ->assertRedirect(Filament::getUrl());

    expect(setupSetting(PlatformSettings::class, 'setup_status'))->toBe('skipped');
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

it('starts on a provider that also makes the embeddings, and says what each provider gives', function (): void {
    setupPage($this->admin)
        ->assertWizardCurrentStep(1)
        ->assertFormSet(['provider' => 'scaleway', 'embedding_provider' => 'qwen'])
        ->assertSee(['Chat · embeddings · speech to text', 'Chat · embeddings · reranking', 'Your own server'])
        ->assertSee('Made with Qwen3 on Scaleway AI, with your Scaleway AI key.')
        ->assertFormFieldHidden('embedding_provider')
        ->assertFormFieldHidden('embedding_api_key');
});

it('checks the key with the provider\'s model list before the next step', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2)
        ->assertSee('Connected to Scaleway AI')
        // The suggested model, from the provider's own suggestions.
        ->assertFormSet(['chat_models' => ['gpt-oss-120b'], 'default_model' => 'gpt-oss-120b']);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/models')
        && $request->header('Authorization') === ['Bearer scw-test']);
});

it('stops on the first step when the provider refuses the key', function (): void {
    Http::fake(['api.scaleway.ai/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'wrong-key'])
        ->goToNextWizardStep()
        ->assertHasFormErrors(['api_key'])
        ->assertWizardCurrentStep(1);
});

it('saves the Scaleway key once, for chat and for embeddings', function (): void {
    fakeScaleway($this->dimensions);
    // A demo agent made before any model was added.
    $agent = createAgent(User::factory()->create());
    $agent->forceFill(['llm_id' => null])->saveQuietly();

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToNextWizardStep()
        ->fillForm(['chat_models' => ['mistral-small-3.2-24b-instruct-2506']])
        ->call('complete')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    $model = Llm::query()->where('external_name', setupSetting(AiSettings::class, 'default_agent_model'))->sole();
    $provider = $model->provider;

    expect($provider->kind)->toBe(LlmProviderEnum::SCALEWAY)
        ->and(LlmProvider::query()->where('kind', LlmProviderEnum::SCALEWAY)->count())->toBe(1)
        ->and($provider->api_key)->toBeNull()
        ->and($provider->base_url)->toBeNull()
        ->and($model->internal_name)->toBe('mistral-small-3.2-24b-instruct-2506')
        ->and($model->context_window)->toBe(128_000)
        ->and(setupSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('scw-test')
        // The default embedding service and model stay unsaved.
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBeNull()
        ->and(setupSetting(AiSettings::class, 'scaleway_embedding_model'))->toBeNull()
        ->and(setupSetting(PlatformSettings::class, 'setup_status'))->toBe('completed')
        ->and($model->isAvailable())->toBeTrue()
        // It gets the default model.
        ->and($agent->refresh()->llm_id)->toBe($model->id);

    Queue::assertPushed(SyncBuiltinConnectorsJob::class);
});

it('makes the embeddings with the OpenRouter key, with a model from its list', function (): void {
    fakeOpenRouter($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'openrouter'])
        // No second service: OpenRouter makes the embeddings.
        ->assertFormFieldHidden('embedding_provider')
        ->assertFormFieldHidden('embedding_api_key')
        // The Scaleway model it started with goes with Scaleway.
        ->assertFormSet(['embedding_provider' => 'openrouter', 'embedding_model' => 'openai/text-embedding-3-small'])
        ->fillForm(['api_key' => 'sk-or-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2)
        // No suggestion is listed: the first model that calls tools is.
        ->assertFormSet(['chat_models' => ['meta-llama/llama-4-maverick']])
        ->assertSee(['Made with OpenRouter, with your OpenRouter key.', 'OpenAI text-embedding-3-small — Good and cheap. The default.', 'BAAI: bge-m3'])
        ->assertDontSee('Text Embedding 3 Small (batch)')
        ->fillForm(['embedding_model' => 'qwen/qwen3-embedding-8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $model = Llm::query()->where('external_name', setupSetting(AiSettings::class, 'default_agent_model'))->sole();

    expect($model->provider->kind)->toBe(LlmProviderEnum::OPENROUTER)
        ->and($model->descriptive_name)->toBe('Llama 4 Maverick')
        ->and($model->features)->toBe(['vision' => true, 'function_calling' => true])
        ->and(setupSetting(AiProviderSettings::class, 'openrouter_api_key'))->toBe('sk-or-test')
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('openrouter')
        ->and(setupSetting(AiSettings::class, 'openrouter_embedding_model'))->toBe('qwen/qwen3-embedding-8b');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/embeddings'
        && $request->header('Authorization') === ['Bearer sk-or-test']
        && $request['model'] === 'qwen/qwen3-embedding-8b'
        && $request['dimensions'] === $this->dimensions);
});

it('asks for an embedding service when the provider makes no embeddings', function (): void {
    fakeGroqAndCohere($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq'])
        ->assertSee('Groq does not make embeddings. Pick a service that does, with its key.')
        ->assertFormFieldVisible('embedding_provider')
        ->fillForm(['api_key' => 'gsk-test', 'embedding_provider' => 'cohere', 'embedding_api_key' => 'co-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(2)
        ->assertFormSet(['embedding_model' => config('services.cohere.embedding_model')])
        ->fillForm(['embedding_model' => 'embed-multilingual-v3.0'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiProviderSettings::class, 'groq_api_key'))->toBe('gsk-test')
        ->and(setupSetting(AiProviderSettings::class, 'cohere_api_key'))->toBe('co-test')
        ->and(setupSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere')
        ->and(setupSetting(AiSettings::class, 'cohere_embedding_model'))->toBe('embed-multilingual-v3.0');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cohere.com/v2/embed'
        && $request->header('Authorization') === ['Bearer co-test']);
});

it('stops on the first step when the embedding service refuses its key', function (): void {
    Http::fake([
        'api.groq.com/openai/v1/models' => Http::response(['data' => []]),
        'api.cohere.com/*' => Http::response(['message' => 'invalid api token'], 401),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test', 'embedding_provider' => 'cohere', 'embedding_api_key' => 'wrong'])
        ->goToNextWizardStep()
        ->assertHasFormErrors(['embedding_api_key']);
});

it('stops on Finish when the embedding model returns another vector size', function (): void {
    fakeScaleway($this->dimensions + 8);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToNextWizardStep()
        ->call('complete')
        ->assertHasFormErrors(['embedding_model']);

    expect(setupSetting(PlatformSettings::class, 'setup_status'))->toBeNull()
        ->and(setupSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});

it('adds a server of your own and takes a typed model name when it lists none', function (): void {
    Http::fake([
        'ollama.test:11434/*' => Http::response('Not found', 404),
        'api.cohere.com/v2/embed' => Http::response(['embeddings' => ['float' => [array_fill(0, $this->dimensions, 0.1)]]]),
    ]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'custom', 'server_name' => 'Office Ollama', 'server_url' => 'http://ollama.test:11434/v1', 'embedding_provider' => 'cohere', 'embedding_api_key' => 'co-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->assertFormFieldVisible('chat_model_id')
        ->fillForm(['chat_model_id' => 'llama3.1:8b'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $server = LlmProvider::query()->where('kind', LlmProviderEnum::CUSTOM)->sole();

    expect($server->name)->toBe('Office Ollama')
        ->and($server->base_url)->toBe('http://ollama.test:11434/v1')
        ->and($server->api_key)->toBeNull()
        ->and($server->llms()->sole()->internal_name)->toBe('llama3.1:8b');
});

it('keeps the embedding service once sources are trained', function (): void {
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
    fakeOpenRouter($this->dimensions);
    $provider = setupSetting(AiSettings::class, 'embedding_provider');

    setupPage($this->admin)
        ->assertSee('Embeddings are already set up')
        ->assertFormFieldHidden('embedding_provider')
        ->fillForm(['provider' => 'openrouter', 'api_key' => 'sk-or-test'])
        ->goToNextWizardStep()
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'embedding_provider'))->toBe($provider);
});

it('lets agents use several chat models, one of them the default', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToNextWizardStep()
        ->fillForm(['chat_models' => ['gpt-oss-120b', 'mistral-small-3.2-24b-instruct-2506'], 'default_model' => 'mistral-small-3.2-24b-instruct-2506'])
        ->call('complete')
        ->assertHasNoFormErrors();

    $added = Llm::query()->whereIn('internal_name', ['gpt-oss-120b', 'mistral-small-3.2-24b-instruct-2506'])->where('is_enabled', true)->get();

    expect($added)->toHaveCount(2)
        ->and(Llm::query()->where('external_name', setupSetting(AiSettings::class, 'default_agent_model'))->value('internal_name'))->toBe('mistral-small-3.2-24b-instruct-2506');
});

it('reads documents with Azure, with its key and endpoint checked', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['customDocumentModels' => ['count' => 0]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->goToWizardStep(3)
        ->assertHasNoFormErrors()
        ->assertFormSet(['document_reader' => 'none'])
        ->assertSee('People cannot upload PDF, Word, slides or images.')
        ->fillForm(['document_reader' => 'azure', 'document_api_key' => 'az-key', 'document_endpoint' => 'https://docs.cognitiveservices.azure.com/'])
        ->goToWizardStep(4)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiProviderSettings::class, 'azure_api_key'))->toBe('az-key')
        ->and(setupSetting(AiProviderSettings::class, 'azure_endpoint'))->toBe('https://docs.cognitiveservices.azure.com')
        ->and(setupSetting(AiSettings::class, 'document_parser'))->toBe('azure');
});

it('warns before going on without a document reader, and goes on once agreed', function (): void {
    fakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->goToWizardStep(3)
        ->assertFormSet(['document_reader' => 'none'])
        ->goToWizardStep(4)
        ->assertActionMounted('noReader')
        ->assertMountedActionModalSee(['Continue without a document reader?', 'PDF, Word, slides and images cannot be read', 'the app reads them itself', 'Continue without one', 'Choose a reader'])
        ->assertWizardCurrentStep(3, 'form')
        ->callMountedAction()
        ->assertWizardCurrentStep(4)
        ->assertSet('noReaderConfirmed', true);

    // Agreed once: Next does not ask again, until the choice changes.
    $page->goToWizardStep(4)->assertActionNotMounted('noReader')->assertWizardCurrentStep(4);
    $page->fillForm(['document_reader' => 'azure'])->fillForm(['document_reader' => 'none'])->assertSet('noReaderConfirmed', false);
});

it('stops on the documents step when Azure refuses the key', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['docs.cognitiveservices.azure.com/*' => Http::response(['error' => ['code' => '401']], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'document_reader' => 'azure', 'document_api_key' => 'wrong', 'document_endpoint' => 'https://docs.cognitiveservices.azure.com'])
        ->goToWizardStep(4)
        ->assertHasFormErrors(['document_api_key']);
});

it('reads documents with the Mistral key of the first step', function (): void {
    // This site is on a private address: Mistral cannot fetch its signed links.
    SafeUrl::resolveHostsUsing(fn (): array => ['127.0.0.1']);
    Http::fake([
        'api.mistral.ai/v1/models' => Http::response(['data' => [['id' => 'mistral-medium-latest', 'capabilities' => ['completion_chat' => true, 'function_calling' => true]]]]),
        'api.mistral.ai/v1/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => array_fill(0, 1024, 0.1)]]]),
    ]);
    config(['chatvia.embeddings.dimensions' => 1024]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'mistral', 'api_key' => 'mistral-test'])
        ->assertFormSet(['document_reader' => 'mistral'])
        ->goToWizardStep(2)
        ->goToWizardStep(3)
        ->assertSee('Uses the Mistral key you entered in Connect AI')
        ->assertFormFieldHidden('document_api_key')
        ->goToWizardStep(4)
        ->assertHasNoFormErrors()
        // Mistral downloads files from a link to this site.
        ->assertSee('Mistral cannot reach this site')
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiProviderSettings::class, 'mistral_api_key'))->toBe('mistral-test')
        ->and(setupSetting(AiSettings::class, 'document_parser'))->toBe('mistral');
})->skip(fn (): bool => app(EmbeddingStorage::class)->dimensions() !== 1024, 'Mistral embeddings are 1024 dimensions only.');

it('starts every optional service on "Not now" and storage on this server, whatever .env suggests', function (): void {
    config(['chatvia.storage.driver' => 's3', 'mail.default' => 'smtp', 'chatvia.ai.transcriber' => 'auto']);

    setupPage($this->admin)
        ->assertFormSet(['document_reader' => 'none', 'website_import' => 'none', 'transcriber' => 'none', 'storage_driver' => 'local', 'mailer' => 'log'])
        ->assertSee(['Reading PDFs and Word', 'Importing websites', 'Audio and video files', 'Mailer'])
        ->assertDontSee('Optional:');
});

it('turns the services left at "Not now" off, so Automatic does not turn them on', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'transcriber'))->toBe('none')
        ->and(setupSetting(AiSettings::class, 'document_parser'))->toBe('none')
        ->and(setupSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBeNull();
});

it('imports websites with Firecrawl, with its key checked', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['api.firecrawl.dev/v2/team/credit-usage' => Http::response(['success' => true, 'data' => ['remainingCredits' => 500]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->goToWizardStep(4)
        ->callMountedAction()
        ->assertSee('To learn from a website, agents need Firecrawl.')
        ->assertFormFieldHidden('firecrawl_api_key')
        ->fillForm(['website_import' => 'firecrawl'])
        ->goToWizardStep(5)
        ->assertHasFormErrors(['firecrawl_api_key' => 'required'])
        ->fillForm(['firecrawl_api_key' => 'fc-test'])
        ->goToWizardStep(5)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBe('fc-test');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.firecrawl.dev/v2/team/credit-usage'
        && $request->header('Authorization') === ['Bearer fc-test']);
});

it('stops on the websites step when Firecrawl refuses the key', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['api.firecrawl.dev/*' => Http::response(['success' => false, 'error' => 'Unauthorized: Invalid token'], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'website_import' => 'firecrawl', 'firecrawl_api_key' => 'wrong'])
        ->goToWizardStep(5)
        ->assertHasFormErrors(['firecrawl_api_key']);
});

it('turns speech to text on with the key of the first step', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->goToWizardStep(5)
        ->assertSee('Agents can then learn from audio and video files and search what is said in them')
        ->fillForm(['transcriber' => 'scaleway'])
        ->assertSee('Uses the Scaleway AI key you entered in Connect AI')
        ->assertFormFieldHidden('transcription_api_key')
        ->goToWizardStep(6)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'transcriber'))->toBe('scaleway');
});

it('asks for the key of a speech service on another account, and checks it', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['api.openai.com/v1/models' => Http::response(['data' => [['id' => 'whisper-1']]])]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'transcriber' => 'openai'])
        ->assertFormFieldVisible('transcription_api_key')
        ->goToWizardStep(2)
        ->goToWizardStep(6)
        ->assertHasFormErrors(['transcription_api_key' => 'required'])
        ->fillForm(['transcription_api_key' => 'sk-openai'])
        ->goToWizardStep(6)
        ->assertHasNoFormErrors()
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(AiSettings::class, 'transcriber'))->toBe('openai')
        ->and(setupSetting(AiProviderSettings::class, 'openai_api_key'))->toBe('sk-openai');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/models'
        && $request->header('Authorization') === ['Bearer sk-openai']);
});

it('asks for each key once: a later step uses the key typed in an earlier one', function (): void {
    setupPage($this->admin)
        // Groq makes no embeddings: OpenAI makes them, with a key typed in the first step.
        ->fillForm(['provider' => 'groq', 'api_key' => 'gsk-test', 'embedding_provider' => 'openai', 'embedding_api_key' => 'sk-openai', 'transcriber' => 'openai'])
        ->assertFormFieldVisible('embedding_api_key')
        ->assertFormFieldHidden('transcription_api_key')
        ->assertSee('Uses the OpenAI key you entered in Connect AI');
});

it('uses a saved key without asking for it again', function (): void {
    config(['services.openai.api_key' => 'sk-saved', 'services.cohere.api_key' => 'co-saved']);

    setupPage($this->admin)
        ->fillForm(['provider' => 'groq', 'embedding_provider' => 'cohere', 'transcriber' => 'openai'])
        ->assertFormFieldHidden('embedding_api_key')
        ->assertFormFieldHidden('transcription_api_key')
        ->assertSee(['Uses your saved Cohere key', 'Uses your saved OpenAI key', 'You can change it in Settings']);
});

it('explains how Mistral reads files kept on this server', function (): void {
    SafeUrl::resolveHostsUsing(fn (): array => ['8.8.8.8']);

    setupPage($this->admin)
        ->fillForm(['document_reader' => 'mistral', 'storage_driver' => 'local'])
        ->assertSee(['Mistral reads the files through a signed link', '/private-storage/', 'works for one hour'])
        ->assertDontSee('Mistral cannot reach this site')
        ->fillForm(['storage_driver' => 's3'])
        ->assertDontSee('Mistral reads the files through a signed link');
});

it('warns when Mistral cannot reach files kept on this server', function (): void {
    SafeUrl::resolveHostsUsing(fn (): array => ['127.0.0.1']);

    setupPage($this->admin)
        ->fillForm(['document_reader' => 'mistral', 'storage_driver' => 'local'])
        ->assertSee(['Mistral cannot reach this site', 'cannot be reached from the internet'])
        ->fillForm(['document_reader' => 'azure'])
        ->assertDontSee('Mistral cannot reach this site');
});

it('stops on the speech step when the service refuses its key', function (): void {
    fakeScaleway($this->dimensions);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'transcriber' => 'openai', 'transcription_api_key' => 'wrong'])
        ->goToWizardStep(6)
        ->assertHasFormErrors(['transcription_api_key']);
});

it('keeps the storage and the mailer an admin saved in Settings', function (): void {
    $storage = app(StorageSettings::class);
    $storage->driver = 's3';
    $storage->save();
    $mail = app(MailSettings::class);
    $mail->mailer = 'smtp';
    $mail->save();
    app()->forgetScopedInstances();

    setupPage($this->admin)->assertFormSet(['storage_driver' => 's3', 'mailer' => 'smtp']);
});

it('asks for S3 details, and stops when the storage does not work', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test', 'storage_driver' => 's3'])
        ->goToWizardStep(7)
        ->assertHasFormErrors(['s3_region', 's3_key', 's3_secret', 'private_bucket', 'public_bucket'])
        ->fillForm([
            's3_endpoint' => 'http://127.0.0.1:9',
            's3_region' => 'us-east-1',
            's3_key' => 'key',
            's3_secret' => 'secret',
            'private_bucket' => 'private',
            'public_bucket' => 'public',
        ])
        ->goToWizardStep(7)
        ->assertHasFormErrors(['private_bucket']);
});

it('sends email with the provider set up, or warns that nobody gets email', function (): void {
    fakeScaleway($this->dimensions);

    $page = setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->assertFormSet(['mailer' => 'log'])
        ->assertSee('Without email, nobody receives emails')
        ->fillForm(['mailer' => 'smtp', 'smtp_host' => 'smtp.example.com', 'smtp_port' => 587, 'smtp_password' => 'mail-secret', 'from_address' => 'support@acme.test'])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(MailSettings::class, 'mailer'))->toBe('smtp')
        ->and(setupSetting(MailSettings::class, 'smtp_host'))->toBe('smtp.example.com')
        ->and(setupSetting(MailSettings::class, 'smtp_password'))->toBe('mail-secret')
        ->and(setupSetting(MailSettings::class, 'from_address'))->toBe('support@acme.test');
});

it('ends on a final step with the choices made, and where the defaults are changed', function (): void {
    fakeScaleway($this->dimensions);

    setupPage($this->admin)
        ->fillForm(['provider' => 'scaleway', 'api_key' => 'scw-test'])
        ->goToWizardStep(2)
        ->fillForm(['chat_models' => ['gpt-oss-120b', 'mistral-small-3.2-24b-instruct-2506'], 'default_model' => 'mistral-small-3.2-24b-instruct-2506'])
        ->goToWizardStep(8)
        ->assertHasNoFormErrors()
        ->assertWizardCurrentStep(8)
        ->assertSeeInOrder([
            'Complete', 'Check your choices, then press',
            'Your choices',
            'AI provider', 'Scaleway AI',
            'Chat models', 'gpt-oss-120b, mistral-small-3.2-24b-instruct-2506 (default)',
            'Embeddings', 'Qwen3 on Scaleway AI', config('services.scalewayai.embedding_model'),
            'Documents', 'Not now',
            'Websites', 'Not now',
            'Speech to text', 'Not now',
            'Storage', 'This server',
            'Mailer', 'Not now',
            'Default values',
            'Knowledge', 'How texts are split into chunks',
            'AI models', 'More chat models, the reranker',
        ])
        ->call('complete')
        ->assertHasNoFormErrors();

    expect(setupSetting(PlatformSettings::class, 'setup_status'))->toBe('completed');
});

it('can be skipped', function (): void {
    setupPage($this->admin)
        ->callAction('skip')
        ->assertRedirect(Filament::getUrl());

    expect(setupSetting(PlatformSettings::class, 'setup_status'))->toBe('skipped');
});

it('does not ask for two-factor authentication', function (): void {
    setupPage($this->admin)
        ->assertDontSee('Set up two-factor authentication first')
        ->assertSee('Finish setup');
});
