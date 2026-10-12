<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AiSettings;
use App\Admin\Settings\Data\SetupData;
use App\Admin\Settings\Data\SetupMailData;
use App\Admin\Settings\MailSettings;
use App\Admin\Settings\PlatformSettings;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Actions\Catalog\AddLlmModelAction;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ProviderAdapters;
use Illuminate\Support\Facades\DB;

/**
 * Saves the admin setup of a new install in one go: the chat provider and
 * its model (the chat model, so new agents start with it), the search
 * service and its key, and email. When the provider is also a service of
 * Settings → AI (its adapter names the config keys: Scaleway, Mistral), its
 * key and address go there too, so that service's search, reranking, speech
 * or document reading work with no second key. Then the setup is marked done.
 */
final readonly class CompleteSetupAction
{
    public function __construct(
        private AddLlmModelAction $addModel,
        private ProviderAdapters $adapters,
        private AiSettings $ai,
        private MailSettings $mail,
        private PlatformSettings $platform,
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

            if ($data->mail instanceof SetupMailData) {
                $this->saveMail($data->mail);
            }

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
            $this->ai->{$property} = $value;
        }
    }

    private function saveMail(SetupMailData $mail): void
    {
        $this->mail->mailer = $mail->provider->value;
        $this->mail->from_address = $mail->fromAddress;

        // Only the settings the provider needs; a blank secret keeps the saved one.
        foreach ($mail->provider->fields() as $field) {
            $value = $mail->values[$field->property] ?? null;

            if (blank($value) && $field->kind === 'secret') {
                continue;
            }

            $this->mail->{$field->property} = MailSettings::cast($field->property, $value);
        }

        $this->mail->save();
    }
}
