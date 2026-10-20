<?php

namespace Domain\Llm;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\LlmProvider;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

/**
 * Base for the OpenAI-compatible chat providers: streaming through Guzzle,
 * non-streaming through the Laravel HTTP client (with token usage tracking),
 * and hooks to adjust the request body per provider. The key and extra
 * headers come from the provider row an admin set up, the address from the
 * provider's adapter. New
 * non-streaming callers use LlmChat, which wraps completion() in typed
 * request and response objects.
 */
abstract class AbstractLlmProvider implements LlmProviderInterface
{
    /**
     * @param  string  $baseUrl  the provider's API address, from its adapter
     * @param  string  $apiKey  the key it calls with, from its adapter (empty for a server without one)
     */
    public function __construct(
        protected readonly LlmProvider $connection,
        protected readonly string $baseUrl,
        protected readonly string $apiKey = '',
    ) {}

    /**
     * Stream a completion request using Guzzle.
     *
     * @param  string  $model  The model identifier
     * @param  list<array<string, mixed>>  $messages  OpenAI-style chat messages
     * @param  array<string, mixed>  $settings  Extra request body keys
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

        // Force the upstream to emit a final chunk with token usage: the reply's
        // token counts come from it. Caller-provided stream_options keys win.
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
     * @param  array<string, mixed>  $data  The full request body
     * @return Response The response
     */
    public function completion(array $data)
    {
        $data = $this->preparePayload($data);

        return $this->client()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $data)
            ->throw();
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
     * The bearer key (when there is one), JSON, and the provider's own extra
     * headers, which win.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $key = $this->getApiKey();

        return array_merge(
            array_filter(['Authorization' => $key !== '' ? 'Bearer '.$key : null]),
            ['Content-Type' => 'application/json'],
            $this->connection->headers ?? [],
        );
    }

    public function getCompletionEndpoint(): string
    {
        return $this->getBaseUrl().'/chat/completions';
    }

    /**
     * Default implementation: no generation lookup.
     *
     * @param  string  $generationId  The generation ID
     * @return array<string, mixed>|null Always returns null
     */
    public function getGeneration(string $generationId): ?array
    {
        return null;
    }

    /**
     * Default implementation: no generation lookup.
     *
     * @return bool False by default
     */
    public function supportsGenerationLookup(): bool
    {
        return false;
    }

    /** The provider's API key, or an empty string when it has none. */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /** The provider's API address, without a trailing slash. */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
