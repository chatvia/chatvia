<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiProvider;

/**
 * Picks the recommended models from what a provider lists: for each slot
 * of {@see AiProvider::presetChatModels()}, and from
 * {@see AiProvider::presetQuickTaskModels()},
 * {@see AiProvider::presetVisionModels()} and
 * {@see AiProvider::presetFileModels()}, the first id it lists, so a model
 * it no longer serves is passed over. A model the provider says cannot do
 * the job (call tools for chat, read images for image descriptions, read
 * images and files and call tools for chat file uploads) is passed over
 * too; one it says nothing about is not.
 *
 * With no chat preset listed, the chat model is the first one that calls
 * tools, else the first. With no quick-task preset listed there is none,
 * so those tasks keep using the chat model. With no vision preset listed
 * there is none either, and image descriptions are turned off; with no
 * file preset listed, chat file uploads are turned off.
 */
class ModelPresets
{
    /**
     * The chat models, default first, one per slot the provider lists.
     *
     * @param  list<RemoteModelData>  $listed  the provider's models, as it lists them
     * @return list<RemoteModelData>
     */
    public function chatModels(?AiProvider $provider, array $listed): array
    {
        $byId = $this->byId($listed);
        $picked = [];

        foreach ($provider?->presetChatModels() ?? [] as $slot) {
            $model = $this->first($slot, $byId, needsTools: true);

            if ($model instanceof RemoteModelData) {
                $picked[$model->id] = $model;
            }
        }

        if ($picked !== []) {
            return array_values($picked);
        }

        $fallback = array_find($listed, fn (RemoteModelData $model): bool => ($model->features['function_calling'] ?? false) === true)
            ?? ($listed[0] ?? null);

        return $fallback === null ? [] : [$fallback];
    }

    /**
     * @param  list<RemoteModelData>  $listed  the provider's models, as it lists them
     */
    public function quickTaskModel(?AiProvider $provider, array $listed): ?RemoteModelData
    {
        return $this->first($provider?->presetQuickTaskModels() ?? [], $this->byId($listed), needsTools: false);
    }

    /**
     * The model for image descriptions: one that reads images.
     *
     * @param  list<RemoteModelData>  $listed  the provider's models, as it lists them
     */
    public function visionModel(?AiProvider $provider, array $listed): ?RemoteModelData
    {
        $byId = $this->byId($listed);

        foreach ($provider?->presetVisionModels() ?? [] as $id) {
            $model = $byId[$id] ?? null;

            if ($model !== null && ($model->features['vision'] ?? null) !== false) {
                return $model;
            }
        }

        return null;
    }

    /**
     * The model for chat file uploads: one that reads PDF files and images
     * and calls tools.
     *
     * @param  list<RemoteModelData>  $listed  the provider's models, as it lists them
     */
    public function fileModel(?AiProvider $provider, array $listed): ?RemoteModelData
    {
        $byId = $this->byId($listed);

        foreach ($provider?->presetFileModels() ?? [] as $id) {
            $model = $byId[$id] ?? null;

            if ($model !== null && ! collect(array_intersect_key($model->features, array_flip(['vision', 'files', 'function_calling'])))->containsStrict(false)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, RemoteModelData>  $byId
     */
    private function first(array $ids, array $byId, bool $needsTools): ?RemoteModelData
    {
        foreach ($ids as $id) {
            $model = $byId[$id] ?? null;

            if ($model !== null && (! $needsTools || ($model->features['function_calling'] ?? null) !== false)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * @param  list<RemoteModelData>  $listed
     * @return array<string, RemoteModelData>
     */
    private function byId(array $listed): array
    {
        $byId = [];

        foreach ($listed as $model) {
            $byId[$model->id] ??= $model;
        }

        return $byId;
    }
}
