<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat completions through OpenRouter: models from many providers with one
 * key. OpenRouter also reports the exact cost of each generation.
 */
class OpenRouter extends AbstractLlmProvider
{
    /**
     * Ask OpenRouter to route streamed chat to the fastest upstream host.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareStreamPayload(array $payload): array
    {
        $payload['provider'] = [
            'sort' => 'throughput',
        ];

        return $payload;
    }

    /**
     * Fetch generation data from OpenRouter.
     * Returns the raw data object with tokens_prompt, tokens_completion, total_cost, etc.
     *
     * @param  string  $generationId  The generation ID
     * @return array<string, mixed>|null The generation data or null on failure
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
}
