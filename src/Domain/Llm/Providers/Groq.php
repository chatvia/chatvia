<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

/**
 * Chat completions through Groq's OpenAI-compatible API.
 */
class Groq extends AbstractLlmProvider
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
    public function getApiKey(): string
    {
        return config('services.groq.api_key');
    }

    /**
     * Get the base URL for Groq.
     *
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return config('services.groq.base_url', 'https://api.groq.com/openai/v1');
    }
}
