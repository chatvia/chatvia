<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\PlatformSettings;
use App\Admin\Settings\SearchSettings;
use App\Admin\Settings\SettingsConfig;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Support\Facades\DB;

/**
 * Saves the admin setup of a new install in one go: hybrid search, the
 * search service with its model and key, and the chat provider and its
 * model (the chat model, so new agents start with it). When the provider is
 * also a service of Settings → AI (its adapter names the config keys:
 * Scaleway, OpenAI, Google Gemini, Mistral), its key and address go there
 * too, so that service's search, reranking, speech or document reading work
 * with no second key.
 * Then the setup is marked done.
 */
final readonly class CompleteSetupAction
{
    public function __construct(
        private AddLlmModelAction $addModel,
        private ProviderAdapters $adapters,
        private AiSettings $ai,
        private SearchSettings $search,
        private PlatformSettings $platform,
        private SettingsConfig $config,
    ) {}

    public function execute(SetupData $data): Llm
    {
        return DB::transaction(function () use ($data): Llm {
            // Running the setup again with the same provider updates its key.
            $provider = LlmProvider::query()->updateOrCreate(
                ['kind' => $data->chatKind, 'base_url' => $data->chatBaseUrl],
                ['name' => $this->adapters->for($data->chatKind)->label(), 'api_key' => $data->chatApiKey, 'is_enabled' => true],
            );

            $model = $this->addModel->execute($provider, $data->chatModel);

            $this->saveAi($data, $model);

            $this->set($this->search, 'hybrid_search', $data->hybridSearch);
            $this->search->save();

            $this->platform->setup_completed_at = now()->toIso8601String();
            $this->platform->save();

            return $model;
        });
    }

    private function saveAi(SetupData $data, Llm $model): void
    {
        $this->ai->default_agent_model = $model->external_name;

        $service = $this->adapters->for($data->chatKind)->settingsConfig();

        if (isset($service['api_key']) && filled($data->chatApiKey)) {
            $this->fill($service['api_key'], $data->chatApiKey);
        }

        if (isset($service['base_url']) && filled($data->chatBaseUrl)) {
            $this->fill($service['base_url'], $data->chatBaseUrl);
        }

        if ($data->embeddingProvider instanceof EmbeddingProvider) {
            $this->ai->embedding_provider = $data->embeddingProvider->value;

            if (filled($data->embeddingModel)) {
                $this->fill($data->embeddingProvider->modelConfig(), $data->embeddingModel);
            }

            if (filled($data->embeddingApiKey)) {
                $this->fill($data->embeddingProvider->apiKeyConfig(), $data->embeddingApiKey);
            }
        }

        $this->ai->save();
    }

    /** Set the Settings → AI property that fills a config key. */
    private function fill(string $configKey, string $value): void
    {
        $property = AiSettings::propertyFor($configKey);

        if ($property !== null) {
            $this->set($this->ai, $property, $value);
        }
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
