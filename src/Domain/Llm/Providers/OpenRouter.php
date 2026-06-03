<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouter extends AbstractLlmProvider
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
        return config('services.openrouter.completion_endpoint');
    }

    /**
     * Fetch generation data from OpenRouter.
     * Returns the raw data object with tokens_prompt, tokens_completion, total_cost, etc.
     *
     * @param  string  $generationId  The generation ID
     * @return array|null The generation data or null on failure
     */
    public function getGenerationCost(string $generationId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->getApiKey(),
            ])
                ->timeout(10)
                ->get($this->getBaseUrl().'/generation', [
                    'id' => $generationId,
                ]);

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Exception $e) {
            Log::warning('Failed to fetch OpenRouter generation data: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Check if the provider supports cost tracking.
     *
     * @return bool True for OpenRouter
     */
    public function supportsCostTracking(): bool
    {
        return true;
    }

    /**
     * Format the model name for OpenRouter.
     * Adds `:nitro` suffix to the model name.
     *
     * @param  string  $model  The model identifier
     * @return string The formatted model name
     */
    protected function formatModel(string $model): string
    {
        return $model.':nitro';
    }

    /**
     * Get the API key for OpenRouter.
     *
     * @return string The API key
     */
    public function getApiKey(): string
    {
        return config('services.openrouter.api_key');
    }

    /**
     * Get the base URL for OpenRouter.
     *
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');
    }
}
