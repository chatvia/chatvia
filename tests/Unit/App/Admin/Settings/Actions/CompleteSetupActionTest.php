<?php

use App\Admin\Settings\Actions\CompleteSetupAction;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\MailSetupData;
use App\Admin\Settings\Data\RerankerSetupData;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\StorageSetupData;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailProvider;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\ObservabilitySettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\StorageSettings;
use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\Bus;

/**
 * CompleteSetupAction: the admin setup of a new install saved in one go. Each
 * key once in Settings → AI providers, the chat models with the default one,
 * the optional services ("Not now" turns one off), and the setup marked done.
 * The setup page around it is in tests/Feature/Admin/AdminSetupTest.php.
 */
beforeEach(function (): void {
    // Saving the embedding service queues a re-index; nothing may run here.
    Bus::fake();
    saveSettings(PlatformSettings::class, ['setup_status' => null]);
});

/**
 * A setup on Scaleway with two chat models, the second the default.
 *
 * @param  array<string, mixed>  $overrides  SetupData arguments by name
 */
function completeSetupData(array $overrides = []): SetupData
{
    return new SetupData(...[
        'chatKind' => LlmProviderKind::Scaleway,
        'apiKey' => 'scw-key',
        'chatModels' => [
            new RemoteModelData(id: 'gpt-oss-120b', name: 'GPT OSS 120B'),
            new RemoteModelData(id: 'mistral-small', name: 'Mistral Small'),
        ],
        'defaultModel' => 'mistral-small',
        ...$overrides,
    ]);
}

it('saves the key once, adds the chat models with the default, and marks the setup done', function (): void {
    $agent = createAgent(User::factory()->create());
    $agent->forceFill(['llm_id' => null])->saveQuietly();

    $default = app(CompleteSetupAction::class)->execute(completeSetupData());

    expect($default->name)->toBe('mistral-small')
        ->and($default->provider->kind)->toBe(LlmProviderKind::Scaleway)
        ->and(Llm::query()->whereIn('name', ['gpt-oss-120b', 'mistral-small'])->where('is_enabled', true)->count())->toBe(2)
        ->and(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('scw-key')
        ->and(savedSetting(AiSettings::class, 'default_agent_model'))->toBe((string) $default->id)
        ->and(savedSetting(PlatformSettings::class, 'setup_status'))->toBe('completed')
        // An agent made before any model was added gets the default one.
        ->and($agent->fresh()->llm_id)->toBe($default->id);
});

it('adds the model for quick tasks and gives the large tasks the default model', function (): void {
    $default = app(CompleteSetupAction::class)->execute(completeSetupData([
        'quickTaskModel' => new RemoteModelData(id: 'qwen3.6-35b-a3b', name: 'Qwen3.6 35B'),
    ]));

    $quick = Llm::query()->where('name', 'qwen3.6-35b-a3b')->sole();

    expect($quick->llm_provider_id)->toBe($default->llm_provider_id)
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBe((string) $quick->id)
        ->and(savedSetting(AiSettings::class, 'eval_judge_model'))->toBe((string) $default->id)
        ->and(savedSetting(AiSettings::class, 'translation_model'))->toBe((string) $default->id)
        ->and(savedSetting(AiSettings::class, 'guardrails_model'))->toBeNull();
});

it('describes images with the model that reads them', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData([
        'visionModel' => new RemoteModelData(id: 'mistral-small-3.2-24b-instruct-2506', name: 'Mistral Small', features: ['vision' => true]),
    ]));

    expect(savedSetting(AiSettings::class, 'images_model'))->toBe((string) Llm::query()->where('name', 'mistral-small-3.2-24b-instruct-2506')->value('id'))
        // On is the default, so it stays unsaved.
        ->and(savedSetting(AiSettings::class, 'describe_images'))->toBeNull();
});

it('turns image descriptions off without a model that reads images', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData());

    expect(savedSetting(AiSettings::class, 'images_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'describe_images'))->toBeFalse();
});

it('answers about chat files with the model that reads them', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData([
        'chatKind' => LlmProviderKind::Mistral,
        'filesModel' => new RemoteModelData(id: 'mistral-large-2512', name: 'Mistral Large'),
    ]));

    expect(savedSetting(AiSettings::class, 'chat_files_model'))->toBe((string) Llm::query()->where('name', 'mistral-large-2512')->value('id'))
        // On is the default, so it stays unsaved.
        ->and(savedSetting(AiSettings::class, 'chat_files_enabled'))->toBeNull();
});

it('turns chat file uploads off without a model that reads files', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData());

    expect(savedSetting(AiSettings::class, 'chat_files_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'chat_files_enabled'))->toBeFalse();
});

it('leaves the task models unset without a model for quick tasks', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData());

    expect(savedSetting(AiSettings::class, 'task_model'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'eval_judge_model'))->toBeNull();
});

it('makes the first model the default when the named one is not among them', function (): void {
    $default = app(CompleteSetupAction::class)->execute(completeSetupData(['defaultModel' => 'not-picked']));

    expect($default->name)->toBe('gpt-oss-120b');
});

it('turns the services left at "Not now" off and leaves the reranker, storage and mail as they are', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData());

    expect(savedSetting(AiSettings::class, 'document_parser'))->toBe('none')
        ->and(savedSetting(AiSettings::class, 'transcriber'))->toBe('none')
        ->and(savedSetting(AiSettings::class, 'reranker'))->toBeNull()
        ->and(savedSetting(AiSettings::class, 'embedding_provider'))->toBeNull()
        ->and(savedSetting(StorageSettings::class, 'driver'))->toBeNull()
        ->and(savedSetting(MailSettings::class, 'mailer'))->toBeNull()
        ->and(savedSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBeNull();
});

it('saves every optional service with its own key', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData([
        'embeddingProvider' => EmbeddingProvider::Cohere,
        'embeddingModel' => 'embed-multilingual-v3.0',
        'embeddingApiKey' => 'co-key',
        'reranker' => new RerankerSetupData(driver: 'custom', model: 'Qwen/Qwen3-Reranker-4B', apiKey: 'own-key', url: ' https://reranker.example.com/v1/ '),
        'documentReader' => AiProvider::Azure,
        'documentApiKey' => 'az-key',
        'documentEndpoint' => 'https://docs.cognitiveservices.azure.com/',
        'transcriber' => AiProvider::OpenAi,
        'transcriptionApiKey' => 'sk-openai',
        'firecrawlApiKey' => 'fc-key',
        'storage' => new StorageSetupData(driver: 's3', endpoint: 'https://s3.example.com', region: 'eu-west-1', key: 's3-key', secret: 's3-secret', pathStyle: true, bucket: 'files'),
        'mail' => new MailSetupData(MailProvider::Smtp, ['smtp_host' => 'smtp.example.com', 'smtp_port' => '587', 'smtp_password' => 'mail-secret'], 'support@acme.test', 'Acme Support'),
    ]));

    expect(savedSetting(AiSettings::class, 'embedding_provider'))->toBe('cohere')
        ->and(savedSetting(AiSettings::class, 'cohere_embedding_model'))->toBe('embed-multilingual-v3.0')
        ->and(savedSetting(AiProviderSettings::class, 'cohere_api_key'))->toBe('co-key')
        // A server of your own keeps its own key, not one in AI providers.
        ->and(savedSetting(AiSettings::class, 'reranker'))->toBe('custom')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_url'))->toBe('https://reranker.example.com/v1')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_model'))->toBe('Qwen/Qwen3-Reranker-4B')
        ->and(savedSetting(AiSettings::class, 'rerank_custom_key'))->toBe('own-key')
        ->and(savedSetting(AiSettings::class, 'document_parser'))->toBe('azure')
        ->and(savedSetting(AiProviderSettings::class, 'azure_api_key'))->toBe('az-key')
        ->and(savedSetting(AiProviderSettings::class, 'azure_endpoint'))->toBe('https://docs.cognitiveservices.azure.com')
        ->and(savedSetting(AiSettings::class, 'transcriber'))->toBe('openai')
        ->and(savedSetting(AiProviderSettings::class, 'openai_api_key'))->toBe('sk-openai')
        ->and(savedSetting(KnowledgeSettings::class, 'firecrawl_api_key'))->toBe('fc-key')
        ->and(savedSetting(StorageSettings::class, 'driver'))->toBe('s3')
        ->and(savedSetting(StorageSettings::class, 's3_bucket'))->toBe('files')
        ->and(savedSetting(StorageSettings::class, 's3_path_style'))->toBeTrue()
        ->and(savedSetting(StorageSettings::class, 's3_secret'))->toBe('s3-secret')
        ->and(savedSetting(MailSettings::class, 'mailer'))->toBe('smtp')
        ->and(savedSetting(MailSettings::class, 'smtp_host'))->toBe('smtp.example.com')
        ->and(savedSetting(MailSettings::class, 'smtp_port'))->toBe(587)
        ->and(savedSetting(MailSettings::class, 'smtp_password'))->toBe('mail-secret')
        ->and(savedSetting(MailSettings::class, 'from_address'))->toBe('support@acme.test')
        ->and(savedSetting(MailSettings::class, 'from_name'))->toBe('Acme Support');
});

it('keeps a saved key when the setup leaves it blank', function (): void {
    saveSettings(AiProviderSettings::class, ['scaleway_api_key' => 'saved-key']);

    app(CompleteSetupAction::class)->execute(completeSetupData(['apiKey' => null]));

    expect(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBe('saved-key');
});

it('leaves a choice that equals its default unsaved, so a later default applies', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData([
        'storage' => new StorageSetupData(driver: 'local'),
    ]));

    expect(savedSetting(StorageSettings::class, 'driver'))->toBeNull();
});

it('keeps Spectra recording by default, and turns it off when asked', function (): void {
    app(CompleteSetupAction::class)->execute(completeSetupData());

    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeNull();

    app(CompleteSetupAction::class)->execute(completeSetupData(['recordAiCalls' => false]));

    expect(savedSetting(ObservabilitySettings::class, 'spectra_enabled'))->toBeFalse();
});

it('refuses a setup without a chat model, and saves nothing', function (): void {
    expect(fn (): Llm => app(CompleteSetupAction::class)->execute(completeSetupData(['chatModels' => []])))
        ->toThrow(InvalidArgumentException::class, 'Pick at least one model for the agents.');

    expect(savedSetting(PlatformSettings::class, 'setup_status'))->toBeNull()
        ->and(savedSetting(AiProviderSettings::class, 'scaleway_api_key'))->toBeNull();
});

it('refuses a server of your own without its address, and saves nothing', function (): void {
    expect(fn (): Llm => app(CompleteSetupAction::class)->execute(completeSetupData(['chatKind' => LlmProviderKind::Custom])))
        ->toThrow(InvalidArgumentException::class, 'A server of your own needs its address.');

    expect(savedSetting(PlatformSettings::class, 'setup_status'))->toBeNull()
        ->and(Llm::query()->where('name', 'gpt-oss-120b')->exists())->toBeFalse();
});
