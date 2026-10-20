<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * Adds one of a provider's models to the catalog, with what the provider said
 * about it. Its alias (`external_name`, what agents and settings store) is
 * the model's id, or the id with the provider's number when another provider
 * already has a model by that id. Adding a model that is already there
 * returns it, turned on again when it was removed.
 */
final class AddLlmModelAction
{
    public function execute(LlmProvider $provider, RemoteModelData $model): Llm
    {
        $llm = Llm::query()->firstOrCreate(
            ['llm_provider_id' => $provider->id, 'internal_name' => $model->id],
            [
                'external_name' => $this->alias($provider, $model->id),
                'descriptive_name' => $model->name,
                'context_window' => $model->contextWindow,
                'features' => $model->features,
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
