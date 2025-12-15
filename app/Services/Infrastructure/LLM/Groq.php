<?php

namespace App\Services\Infrastructure\LLM;

class Groq extends AbstractLLMProvider
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
        return config('services.groq.completion_endpoint');
    }

    /**
     * Get the API key for Groq.
     *
     * @return string The API key
     */
    protected function getApiKey(): string
    {
        return config('services.groq.api_key');
    }

    /**
     * Get the base URL for Groq.
     *
     * @return string The base URL
     */
    protected function getBaseUrl(): string
    {
        return config('services.groq.base_url', 'https://api.groq.com/openai/v1');
    }
}
