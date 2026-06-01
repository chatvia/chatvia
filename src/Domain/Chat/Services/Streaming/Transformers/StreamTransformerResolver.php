<?php

namespace Domain\Chat\Services\Streaming\Transformers;

use App\LLM\Contracts\LLMProviderInterface;

class StreamTransformerResolver
{
    public function __construct(
        private readonly OpenAiCompatibleTransformer $openAiCompatible,
    ) {}

    /**
     * Pick the right transformer for the given provider.
     *
     * All current providers (OpenRouter, Groq, Mistral, Scaleway) are
     * OpenAI-compatible. To add a non-OpenAI provider (e.g. Anthropic),
     * extend this method to branch on the provider class.
     */
    public function for(LLMProviderInterface $provider): StreamTransformer
    {
        return $this->openAiCompatible;
    }
}
