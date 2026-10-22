<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Models\Llm;

/**
 * Chat completions through OpenAI's own API, which is stricter than the
 * servers that copy it: it has no `top_k`, and its newer models refuse
 * `max_tokens`. Which effort and sampling a model takes is decided by the
 * app from the model's facts ({@see Llm::requestSettings()}),
 * not here.
 */
class OpenAi extends AbstractLlmProvider
{
    /**
     * The agent's stored llmSettings always include `top_k`, and the app's
     * own tasks cap replies with `max_tokens`, which every OpenAI chat model
     * also takes as `max_completion_tokens`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        unset($payload['top_k']);

        if (array_key_exists('max_tokens', $payload)) {
            $payload['max_completion_tokens'] ??= $payload['max_tokens'];
            unset($payload['max_tokens']);
        }

        return $payload;
    }
}
