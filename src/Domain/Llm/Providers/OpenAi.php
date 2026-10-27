<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Models\Llm;
use Domain\Llm\Providers\Concerns\StreamsThroughResponses;

/**
 * Chat completions through OpenAI's own API, which is stricter than the
 * servers that copy it: it has no `top_k`, and its newer models refuse
 * `max_tokens`. Which effort and sampling a model takes is decided by the
 * app from the model's facts ({@see Llm::requestSettings()}),
 * not here.
 *
 * Chat turns stream through its Responses API ({@see StreamsThroughResponses}),
 * the only one of its APIs that sends a summary of a reasoning model's
 * thinking. The app's own single calls use Chat Completions.
 */
class OpenAi extends AbstractLlmProvider
{
    use StreamsThroughResponses;

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
