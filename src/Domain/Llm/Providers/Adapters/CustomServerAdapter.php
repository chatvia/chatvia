<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Enums\LlmProviderEnum;

/**
 * Any other server with an OpenAI-compatible API: one an admin runs (vLLM,
 * Ollama, LM Studio) or a hosted one. The address is required and the key
 * optional; a server without a model list takes a model name typed by hand.
 */
final class CustomServerAdapter extends OpenAiCompatibleAdapter
{
    public function kind(): LlmProviderEnum
    {
        return LlmProviderEnum::CUSTOM;
    }

    public function label(): string
    {
        return 'OpenAI-compatible server';
    }

    public function description(): string
    {
        return 'Your own server (vLLM, Ollama, LM Studio) or another service with an OpenAI-compatible API.';
    }

    public function baseUrlHelp(): string
    {
        return 'The address of the OpenAI-compatible API, usually ending in /v1.';
    }

    public function needsApiKey(): bool
    {
        return false;
    }
}
