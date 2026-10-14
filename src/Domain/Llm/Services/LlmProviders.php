<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;

/**
 * The client for a model's provider: every call to a model goes to the
 * provider it belongs to, with that provider's address and key, through the
 * client of its kind's adapter. Tests swap this class to answer with a fake
 * client.
 */
class LlmProviders
{
    public function __construct(
        private readonly ProviderAdapters $adapters,
    ) {}

    /**
     * @throws LlmProviderUnavailableException
     */
    public function for(Llm $model): LlmProviderInterface
    {
        return $this->forProvider($model->provider);
    }

    /**
     * @throws LlmProviderUnavailableException
     */
    public function forProvider(LlmProvider $provider): LlmProviderInterface
    {
        if (! $provider->is_enabled) {
            throw LlmProviderUnavailableException::disabled($provider);
        }

        $adapter = $this->adapters->for($provider->kind);

        if (! $adapter->isConnected($provider)) {
            throw LlmProviderUnavailableException::notConnected($provider);
        }

        return $adapter->client($provider);
    }
}
