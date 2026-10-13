<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

/**
 * Chat completions through Mistral's API. Mistral can read a whole PDF passed
 * by URL, so it is also the document-reading LLM (see DocumentLlm).
 */
class Mistral extends AbstractLlmProvider
{
    /**
     * Mistral chat completions rejects `top_k` (error 3051), but the agent's
     * stored llmSettings always include it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        unset($payload['top_k']);

        return $payload;
    }
}
