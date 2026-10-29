<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Support\Radar\IssueRaised;
use Support\Radar\IssueTypeKey;

/**
 * The client for a model's provider: every call to a model goes to the
 * provider it belongs to, with that provider's address and key, through the
 * client of its kind's adapter. A provider that is turned off or has no key
 * stops the call, and says so ({@see IssueRaised}). Tests swap
 * this class to answer with a fake client.
 */
class LlmProviders
{
    public function __construct(
        private readonly ProviderAdapters $adapters,
    ) {}

    /**
     * @throws LlmProviderUnavailableException
     */
    public function for(Llm $model): ChatCompletionProvider
    {
        return $this->forProvider($model->provider);
    }

    /**
     * @throws LlmProviderUnavailableException
     */
    public function forProvider(LlmProvider $provider): ChatCompletionProvider
    {
        if (! $provider->is_enabled) {
            $this->raise($provider, IssueTypeKey::TURNED_OFF);

            throw LlmProviderUnavailableException::disabled($provider);
        }

        $adapter = $this->adapters->for($provider->kind);

        if (! $adapter->isConnected($provider)) {
            $this->raise($provider, IssueTypeKey::KEY_MISSING);

            throw LlmProviderUnavailableException::notConnected($provider);
        }

        return $adapter->client($provider);
    }

    /**
     * Tells Radar a saved provider of an AI account stopped a call. An
     * unsaved one (a Settings check) and a server of your own say nothing.
     */
    private function raise(LlmProvider $provider, string $type): void
    {
        $account = AiProvider::forChatKind($provider->kind);

        if ($provider->exists && $account instanceof AiProvider) {
            IssueRaised::dispatch($account->value, $type);
        }
    }
}
