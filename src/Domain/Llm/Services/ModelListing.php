<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Catalog\ModelListData;
use Domain\Llm\Enums\ModelListStatus;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Reads a provider's chat models, which also checks its key and address:
 * for a saved row, or an unsaved one with a key typed but not saved yet.
 * Used by the admin setup and Settings → AI providers and AI models.
 */
final readonly class ModelListing
{
    public function __construct(
        private ProviderAdapters $adapters,
    ) {}

    public function list(LlmProvider $provider): ModelListData
    {
        $adapter = $this->adapters->for($provider->kind);

        try {
            $models = $adapter->models($provider);
        } catch (RequestException $e) {
            return new ModelListData($adapter->refusesKey($e->response) ? ModelListStatus::KeyRefused : ModelListStatus::Failed, httpStatus: $e->response->status());
        } catch (ConnectionException) {
            return new ModelListData(ModelListStatus::Unreachable);
        }

        return $models->isEmpty()
            ? new ModelListData(ModelListStatus::Empty)
            : new ModelListData(ModelListStatus::Listed, $models->values()->all());
    }
}
