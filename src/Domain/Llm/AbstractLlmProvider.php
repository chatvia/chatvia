<?php

namespace Domain\Llm;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use Domain\Llm\Usage\TokenUsageContext;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractLlmProvider implements LlmProviderInterface
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
        $json = array_merge([
            'model' => $model,
            'messages' => $messages,
            'stream' => true,
        ], $settings);

        //        if (static::class === Scaleway::class) {
        //            $json['reasoning_effort'] = 'none';
        //        }

        // Force the upstream to emit a final chunk with token usage so Spectra
        // can record cost/tokens. Caller-provided stream_options keys win.
        $json['stream_options'] = ($json['stream_options'] ?? []) + ['include_usage' => true];

        if (static::class === OpenRouter::class) {
            $json['provider'] = [
                'sort' => 'throughput',
            ];
        }

        if (static::class === Mistral::class) {
            // Mistral chat completions rejects `top_k` (error 3051), but the
            // agent's stored llmSettings always include it.
            unset($json['top_k']);
        }

        return app(GuzzleClient::class)->post($this->getCompletionEndpoint(), [
            'headers' => $this->headers(),
            'json' => $json,
            'stream' => true,
            'read_timeout' => 120,
        ]);
    }

    /**
     * Execute a completion request using Laravel HTTP client.
     *
     * @param  array  $data  The request data
     * @return Response The response
     */
    public function completion(array $data)
    {
        //        if (static::class === Scaleway::class) {
        //            $data += ['reasoning_effort' => 'none'];
        //        }

        if (static::class === Mistral::class) {
            unset($data['top_k']);
        }

        $response = $this->client()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $data)
            ->throw();

        // Attribute this non-streaming call's tokens to the active workspace
        // (rewrite, title, chunker, vision, tables, analysis, ...) for analytics.
        // No scope open → no-op. Streaming replies report usage out-of-band and are
        // recorded by StoreMessageAction, so stream() is deliberately not hooked.
        app(TokenUsageContext::class)->addFromResponse($response);

        return $response;
    }

    public function client(): PendingRequest
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
    abstract public function getApiKey(): string;

    /**
     * Get the base URL for the provider.
     *
     * @return string The base URL
     */
    abstract public function getBaseUrl(): string;
}
