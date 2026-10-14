<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderEnum;

/**
 * OpenAI's own API. Its model list carries ids only, and also lists its
 * embedding, speech and image models, which are left out by id.
 */
final class OpenAiAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderEnum
    {
        return LlmProviderEnum::OPENAI;
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
}
