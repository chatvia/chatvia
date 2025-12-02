<?php

namespace App\Services\Infrastructure\LLM;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class OpenRouter
{
    public function stream($model, array $messages, array $settings = []): ResponseInterface
    {
        return app(GuzzleClient::class)->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model.':nitro',
                'messages' => $messages,
                'stream' => true,
            ],

            'stream' => true,
            ...$settings,
        ]);
    }

    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openrouter.completion_endpoint'), $data)
            ->throw();
    }

    /**
     * Fetch generation data from OpenRouter.
     * Returns the raw data object with tokens_prompt, tokens_completion, total_cost, etc.
     */
    public static function getGeneration(string $generationId): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            ])
                ->timeout(10)
                ->get(config('services.openrouter.base_url', 'https://openrouter.ai/api/v1').'/generation', [
                    'id' => $generationId,
                ]);

            if ($response->successful()) {
                return $response->json('data');
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to fetch OpenRouter generation data: '.$e->getMessage());
        }

        return null;
    }

    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ];
    }
}
