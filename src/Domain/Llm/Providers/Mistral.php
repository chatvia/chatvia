<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

class Mistral extends AbstractLlmProvider
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

    /**
     * Get the API key for Mistral.
     *
     * @return string The API key
     */
    public function getApiKey(): string
    {
        return config('services.mistral.api_key');
    }

    /**
     * Get the base URL for Mistral.
     *
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return config('services.mistral.base_url', 'https://api.mistral.ai/v1');
    }
}
