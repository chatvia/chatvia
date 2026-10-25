<?php

declare(strict_types=1);

namespace Domain\Llm\Actions\Catalog;

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\ModelPresets;

/**
 * Adds a model the setup picked from a provider's presets
 * ({@see ModelPresets}), with its "Reasons" switch off:
 * thinking makes replies slower and costlier, so the admin turns it on by
 * choice. A model that was already in the catalog keeps the switch the admin
 * left it at.
 */
final readonly class AddPresetLlmModelAction
{
    public function __construct(
        private AddLlmModelAction $addModel,
    ) {}

    public function execute(LlmProvider $provider, RemoteModelData $model): Llm
    {
        $llm = $this->addModel->execute($provider, $model);

        if ($llm->wasRecentlyCreated) {
            $llm->update(['is_reasoning_enabled' => false]);
        }

        return $llm;
    }
}
