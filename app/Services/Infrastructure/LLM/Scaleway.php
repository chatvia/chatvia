<?php

namespace App\Services\Infrastructure\LLM;

class Scaleway extends AbstractLLMProvider
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
        return 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1/chat/completions';
    }

    /**
     * Get the API key for Groq.
     *
     * @return string The API key
     */
    public function getApiKey(): string
    {
        return config('services.scalewayai.api_key');
    }

    /**
     * Get the base URL for Groq.
     *
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return 'https://api.scaleway.ai/ce82c941-d642-4fe0-94d7-b89bee83dd9d/v1';
    }
}
