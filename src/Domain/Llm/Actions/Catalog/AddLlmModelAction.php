<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelsDevCatalog;

/**
 * Adds one of a provider's models to the catalog, with what the provider said
 * about it and its capabilities from the models.dev catalog (whether it
 * reasons, its effort values, whether it takes a temperature). For a model
 * the catalog does not list, whether it reasons comes from the provider's own
 * list (OpenRouter's), when it says. Its alias
 * (`external_name`, what agents and settings store) is the model's id, or
 * the id with the provider's number when another provider already has a
 * model by that id. Adding a model that is already there returns it, turned
 * on again when it was removed.
 */
final readonly class AddLlmModelAction
{
    public function __construct(
        private ModelsDevCatalog $catalog,
    ) {}

    public function execute(LlmProvider $provider, RemoteModelData $model): Llm
    {
        $listed = $model->features['reasoning'] ?? null;

        $llm = Llm::query()->firstOrCreate(
            ['llm_provider_id' => $provider->id, 'internal_name' => $model->id],
            [
                'external_name' => $this->alias($provider, $model->id),
                'descriptive_name' => $model->name,
                'context_window' => $model->contextWindow,
                'features' => $model->features,
                'capabilities' => $this->catalog->entry($provider->kind, $model->id)
                    ?? (is_bool($listed) ? ['reasoning' => $listed] : null),
            ],
        );

        if (! $llm->is_enabled) {
            $llm->update(['is_enabled' => true]);
        }

        return $llm;
    }

    private function alias(LlmProvider $provider, string $id): string
    {
        return Llm::query()->where('external_name', $id)->exists() ? "{$id}-{$provider->id}" : $id;
    }
}
