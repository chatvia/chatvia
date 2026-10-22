<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\OpenAi;

/**
 * OpenAI's own API. Its model list carries ids only, and also lists its
 * embedding, speech and image models, which are left out by id. Its chat
 * calls go through {@see OpenAi}, which fits the request body to its models.
 */
final class OpenAiAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::OpenAi;
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function description(): string
    {
        return 'The GPT models.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    public function keysUrl(): string
    {
        return 'https://platform.openai.com/api-keys';
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new OpenAi($provider, $this->baseUrl($provider), $this->apiKey($provider) ?? '');
    }
}
