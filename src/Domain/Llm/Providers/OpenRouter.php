<?php

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chat completions through OpenRouter: models from many providers with one
 * key. OpenRouter can also look up a generation's token counts afterwards.
 */
class OpenRouter extends AbstractLlmProvider
{
    /**
     * OpenRouter takes the effort in its own `reasoning` object, the same for
     * every model. The thinking is left out of the reply unless the turn
     * shows it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        $effort = $payload[self::REASONING_EFFORT] ?? null;
        unset($payload[self::REASONING_EFFORT]);

        if (filled($effort)) {
            $payload['reasoning'] = ['effort' => $effort] + (($payload[self::SHOW_REASONING] ?? false) ? [] : ['exclude' => true]);
        }

        return $payload;
    }

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
     * Returns the raw data object with tokens_prompt, tokens_completion, etc.
     *
     * @param  string  $generationId  The generation ID
     * @return array<string, mixed>|null The generation data or null on failure
     */
    public function getGeneration(string $generationId): ?array
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
     * Check if the provider can look up a generation by its id.
     *
     * @return bool True for OpenRouter
     */
    public function supportsGenerationLookup(): bool
    {
        return true;
    }
}
