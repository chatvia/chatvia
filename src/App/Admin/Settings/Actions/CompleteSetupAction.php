<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\AiProviderSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\MailSetupData;
use App\Admin\Settings\Data\RerankerSetupData;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\StorageSetupData;
use App\Admin\Settings\KnowledgeSettings;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SettingsConfig;
use App\Admin\Settings\SetupStatus;
use App\Admin\Settings\StorageSettings;
use App\Admin\Settings\TaskModelPreset;
use Domain\Agent\Actions\Settings\AssignModelToAgentsWithoutOneAction;
use Domain\Integration\Mcp\Jobs\SyncBuiltinConnectorsJob;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Actions\Catalog\EnsureLlmProviderAction;
use Domain\Llm\Actions\Catalog\SaveCustomLlmServerAction;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Saves the admin setup of a new install in one go: each key once in
 * Settings → AI providers (for everything that provider does), the chat
 * models agents can use with the default one, the model for quick tasks
 * with the task models it brings ({@see TaskModelPreset}), the model for
 * image descriptions (none turns image descriptions off), the embedding service and
 * model, the reranker, the document reader, the Firecrawl key, speech to
 * text, voice conversations, where files are kept, and how email is sent. A service left at "Not now" is turned
 * off, so "Automatic" does not turn it on. Agents made before any model
 * was added (the demo data) get the default model. Then the setup is
 * marked done. A value that still equals its default
 * stays unsaved, as in the Settings pages.
 */
final readonly class CompleteSetupAction
{
    public function __construct(
        private EnsureLlmProviderAction $ensureProvider,
        private SaveCustomLlmServerAction $saveServer,
        private AddLlmModelAction $addModel,
        private AssignModelToAgentsWithoutOneAction $assignModel,
        private AiProviderSettings $providers,
        private AiSettings $ai,
        private KnowledgeSettings $knowledge,
        private StorageSettings $storage,
        private MailSettings $mail,
        private PlatformSettings $platform,
        private SettingsConfig $config,
    ) {}

    /**
     * @return Llm the default model for new agents
     */
    public function execute(SetupData $data): Llm
    {
        if ($data->chatModels === []) {
            throw new InvalidArgumentException('Pick at least one model for the agents.');
        }

        $default = DB::transaction(function () use ($data): Llm {
            $account = AiProvider::forChatKind($data->chatKind);

            if ($account instanceof AiProvider) {
                $this->saveKey($account, $data->apiKey);
                $provider = $this->ensureProvider->execute($data->chatKind);
            } else {
                $provider = $this->saveServer->execute($data->server ?? throw new InvalidArgumentException('A server of your own needs its address.'));
            }

            $models = array_map(fn (RemoteModelData $model): Llm => $this->addModel->execute($provider, $model), $data->chatModels);
            $default = array_find($models, fn (Llm $model): bool => $model->name === $data->defaultModel) ?? $models[0];
            $this->ai->default_agent_model = (string) $default->id;
            $this->assignModel->execute($default);

            $quick = $data->quickTaskModel instanceof RemoteModelData ? $this->addModel->execute($provider, $data->quickTaskModel) : null;
            $vision = $data->visionModel instanceof RemoteModelData ? $this->addModel->execute($provider, $data->visionModel) : null;

            foreach (TaskModelPreset::values($default, $quick, $vision) as $property => $value) {
                $this->set($this->ai, $property, $value);
            }

            $this->set($this->knowledge, 'describe_images', $vision instanceof Llm);

            if ($data->embeddingProvider instanceof EmbeddingProvider) {
                $this->set($this->ai, 'embedding_provider', $data->embeddingProvider->value);

                if (filled($data->embeddingModel)) {
                    $this->set($this->ai, AiSettings::propertyFor($data->embeddingProvider->modelConfig()) ?? '', $data->embeddingModel);
                }

                $this->saveKey($data->embeddingProvider->account(), $data->embeddingApiKey);
            }

            if ($data->reranker instanceof RerankerSetupData) {
                $this->saveReranker($data->reranker);
            }

            if ($data->documentReader instanceof AiProvider) {
                $this->saveKey($data->documentReader, $data->documentApiKey);
                $this->saveAddress($data->documentReader, $data->documentEndpoint);
                $this->set($this->ai, 'document_parser', $data->documentReader->value);
            } else {
                $this->set($this->ai, 'document_parser', 'none');
            }

            if ($data->transcriber instanceof AiProvider) {
                $this->saveKey($data->transcriber, $data->transcriptionApiKey);
                $this->set($this->ai, 'transcriber', $data->transcriber->value);
            } else {
                $this->set($this->ai, 'transcriber', 'none');
            }

            if ($data->voice instanceof AiProvider) {
                $this->saveKey($data->voice, $data->voiceApiKey);
                $this->set($this->ai, 'voice', $data->voice->value);
            } else {
                $this->set($this->ai, 'voice', 'none');
            }

            if (filled($data->firecrawlApiKey)) {
                $this->knowledge->firecrawl_api_key = $data->firecrawlApiKey;
            }

            if ($data->storage instanceof StorageSetupData) {
                $this->saveStorage($data->storage);
            }

            if ($data->mail instanceof MailSetupData) {
                $this->saveMail($data->mail);
            }

            $this->providers->save();
            $this->ai->save();
            $this->knowledge->save();
            $this->storage->save();
            $this->mail->save();

            $this->platform->setup_status = SetupStatus::Completed->value;
            $this->platform->save();

            return $default;
        });

        // Chatvia's own tools (charts, forms) need the embedding service that
        // was just configured. After the transaction, so the job sees it.
        SyncBuiltinConnectorsJob::dispatch();

        return $default;
    }

    /** Keep a provider's key in Settings → AI providers; a blank one keeps the saved key. */
    private function saveKey(AiProvider $account, ?string $key): void
    {
        $property = AiProviderSettings::propertyFor($account->apiKeyConfig());

        if ($property !== null && filled($key)) {
            $this->providers->{$property} = $key;
        }
    }

    private function saveAddress(AiProvider $account, ?string $address): void
    {
        $config = $account->baseUrlConfig();
        $property = $config !== null ? AiProviderSettings::propertyFor($config) : null;

        if ($property !== null && filled($address)) {
            $this->set($this->providers, $property, rtrim(trim($address), '/'));
        }
    }

    /**
     * The reranker, with its model and key: OpenRouter's and Cohere's keys go
     * to Settings → AI providers, a server of your own keeps its own.
     */
    private function saveReranker(RerankerSetupData $reranker): void
    {
        $this->set($this->ai, 'reranker', $reranker->driver);

        $account = AiProvider::tryFrom($reranker->driver);

        if ($account instanceof AiProvider) {
            $this->saveKey($account, $reranker->apiKey);
        }

        $model = match ($reranker->driver) {
            'openrouter' => 'openrouter_rerank_model',
            'cohere' => 'cohere_rerank_model',
            'custom' => 'rerank_custom_model',
            default => null,
        };

        if ($model !== null && filled($reranker->model)) {
            $this->set($this->ai, $model, $reranker->model);
        }

        if ($reranker->driver !== 'custom') {
            return;
        }

        $this->set($this->ai, 'rerank_custom_url', Str::rtrim(Str::trim($reranker->url ?? ''), '/'));

        if (filled($reranker->apiKey)) {
            $this->ai->rerank_custom_key = $reranker->apiKey;
        }
    }

    private function saveStorage(StorageSetupData $storage): void
    {
        $this->set($this->storage, 'driver', $storage->driver);

        if (! $storage->usesS3()) {
            return;
        }

        foreach ([
            's3_endpoint' => $storage->endpoint,
            's3_region' => $storage->region,
            's3_key' => $storage->key,
            's3_path_style' => $storage->pathStyle,
            's3_bucket' => $storage->bucket,
        ] as $property => $value) {
            $this->set($this->storage, $property, StorageSettings::cast($property, $value));
        }

        if (filled($storage->secret)) {
            $this->storage->s3_secret = $storage->secret;
        }
    }

    private function saveMail(MailSetupData $mail): void
    {
        $this->set($this->mail, 'mailer', $mail->provider->value);

        foreach ($mail->provider->fields() as $field) {
            $value = MailSettings::cast($field->property, $mail->values[$field->property] ?? null);

            if ($field->kind === 'secret') {
                if ($value !== null) {
                    $this->mail->{$field->property} = $value;
                }

                continue;
            }

            $this->set($this->mail, $field->property, $value);
        }

        $this->set($this->mail, 'from_address', MailSettings::cast('from_address', $mail->fromAddress));
        $this->set($this->mail, 'from_name', MailSettings::cast('from_name', $mail->fromName));
    }

    /**
     * Set a property, except a value that still equals its default and was
     * never saved: it stays unsaved, as in the Settings pages, so a later
     * change of the default applies.
     */
    private function set(AdminSettings $settings, string $property, mixed $value): void
    {
        $default = $settings::cast($property, $this->config->defaultOf($settings::class, $property));

        if ($settings->{$property} === null && $value === $default) {
            return;
        }

        $settings->{$property} = $value;
    }
}
