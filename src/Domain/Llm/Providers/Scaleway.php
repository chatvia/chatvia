<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

class Scaleway extends AbstractLlmProvider
{
    /**
     * Get the headers for API requests.
     *
     * @return array The headers array
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
