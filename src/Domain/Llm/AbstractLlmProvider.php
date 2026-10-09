<?php

namespace Domain\Llm;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Usage\TokenUsageContext;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * Base for the OpenAI-compatible chat providers: streaming through Guzzle,
 * non-streaming through the Laravel HTTP client (with token usage tracking),
 * and hooks to adjust the request body per provider. New non-streaming callers
 * use LlmChat, which wraps completion() in typed request and response objects.
 */
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

        $json = $this->preparePayload($json);

        // Force the upstream to emit a final chunk with token usage so Spectra
        // can record cost/tokens. Caller-provided stream_options keys win.
        $json['stream_options'] = ($json['stream_options'] ?? []) + ['include_usage' => true];

        $json = $this->prepareStreamPayload($json);

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
        $data = $this->preparePayload($data);

        $response = $this->client()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $data)
            ->throw();

        // Attribute this non-streaming call's tokens to the active workspace
        // (rewrite, title, chunker, vision, tables, analysis, ...) for analytics.
        // No scope open → no-op. Streaming replies report usage out-of-band and are
        // recorded by StoreAssistantMessageAction, so stream() is deliberately not hooked.
        app(TokenUsageContext::class)->addFromResponse($response);

        return $response;
    }

    /**
     * Adjust the request body for this provider before it is sent, for both
     * streaming and non-streaming calls. Default: no change.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function preparePayload(array $payload): array
    {
        return $payload;
    }

    /**
     * Adjust the request body of a streaming call only, after preparePayload()
     * and the usage option are applied. Default: no change.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function prepareStreamPayload(array $payload): array
    {
        return $payload;
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
