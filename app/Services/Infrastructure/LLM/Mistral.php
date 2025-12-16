<?php

namespace App\Services\Infrastructure\LLM;

class Mistral extends AbstractLLMProvider
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
     * Get the API key for Mistral.
     *
     * @return string The API key
     */
    protected function getApiKey(): string
    {
        return config('services.mistral.api_key');
    }

    /**
     * Get the base URL for Mistral.
     *
     * @return string The base URL
     */
    protected function getBaseUrl(): string
    {
        return config('services.mistral.base_url', 'https://api.mistral.ai/v1');
    }
}
