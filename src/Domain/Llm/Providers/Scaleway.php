<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

/**
 * Chat completions through the Scaleway Generative APIs (open-weight models
 * such as Qwen3, hosted in the EU).
 */
class Scaleway extends AbstractLlmProvider
{
    /**
     * Get the headers for API requests.
     *
     * @return array<string, string> The headers array
     */
    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getApiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Get the completion endpoint URL.
     *
     * @return string The completion endpoint
     */
    public function getCompletionEndpoint(): string
    {
        return $this->getBaseUrl().'/chat/completions';
    }

    /**
     * Scaleway's Qwen3 reasoning models (qwen3.*) think by default: that adds
     * several seconds before the first answer token plus a chunk of hidden
     * chain-of-thought. This pipeline is RAG — we want the grounded answer
     * straight away — so default reasoning OFF for those models via
     * `reasoning_effort=none`.
     *
     * Scoped to qwen ONLY: other Scaleway models (the analytics / guardrail
     * instruct models) strictly validate the request body and return 400 on an
     * unknown `reasoning_effort` field, so they must never receive it. A caller
     * that set `reasoning_effort` itself is always left untouched.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        if (array_key_exists('reasoning_effort', $payload)) {
            return $payload;
        }

        if (! str_contains(strtolower((string) ($payload['model'] ?? '')), 'qwen')) {
            return $payload;
        }

        $payload['reasoning_effort'] = 'none';

        return $payload;
    }

    /**
     * Get the API key for Scaleway.
     *
     * @return string The API key
     */
    public function getApiKey(): string
    {
        return config('services.scalewayai.api_key');
    }

    /**
     * Get the base URL for Scaleway Generative APIs.
     *
     * @return string The base URL, without a trailing slash
     */
    public function getBaseUrl(): string
    {
        return self::baseUrl();
    }

    /**
     * The configured Scaleway Generative APIs base URL. Shared by the chat
     * provider, the Qwen3 embedding/rerank client and audio transcription so
     * the project URL is set in one place (`services.scalewayai.endpoint`).
     */
    public static function baseUrl(): string
    {
        return rtrim((string) config('services.scalewayai.endpoint'), '/');
    }
}
