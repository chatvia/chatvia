<?php

namespace App\Services\Infrastructure\LLM;

use App\Contracts\LLMProviderInterface;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractLLMProvider implements LLMProviderInterface
{
    /**
     * Stream a completion request using Guzzle.
     *
     * @param  string  $model  The model identifier
     * @param  array  $messages  The messages array
     * @param  array  $settings  Additional settings for the request
     * @return ResponseInterface The streaming response
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        $json = [
            'model' => $this->formatModel($model),
            'messages' => $messages,
            'stream' => true,
            'max_tokens' => 1024,
        ];

        if (static::class === OpenRouter::class) {
            $json['provider'] = [
                'sort' => 'throughput',
            ];
        }

        return app(GuzzleClient::class)->post($this->getCompletionEndpoint(), [
            'headers' => $this->headers(),
            'json' => $json,
            'stream' => true,
//            ...$settings,
        ]);
    }

    /**
     * Execute a completion request using Laravel HTTP client.
     *
     * @param  array  $data  The request data
     * @return \Illuminate\Http\Client\Response The response
     */
    public function completion(array $data)
    {
        return $this->client()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $data)
            ->throw();
    }

    /**
     * Get a configured HTTP client instance.
     *
     * @return \Illuminate\Http\Client\PendingRequest The HTTP client
     */
    public function client()
    {
        return Http::withHeaders($this->headers());
    }

    /**
     * Default implementation: no cost tracking support.
     *
     * @param  string  $generationId  The generation ID
     * @return array|null Always returns null
     */
    public function getGenerationCost(string $generationId): ?array
    {
        return null;
    }

    /**
     * Default implementation: no cost tracking support.
     *
     * @return bool False by default
     */
    public function supportsCostTracking(): bool
    {
        return false;
    }

    /**
     * Format the model name.
     * Override this method in subclasses if model formatting is needed.
     *
     * @param  string  $model  The model identifier
     * @return string The formatted model name
     */
    protected function formatModel(string $model): string
    {
        return $model;
    }

    /**
     * Get the API key for the provider.
     *
     * @return string The API key
     */
    abstract protected function getApiKey(): string;

    /**
     * Get the base URL for the provider.
     *
     * @return string The base URL
     */
    abstract protected function getBaseUrl(): string;
}
