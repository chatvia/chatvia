<?php

namespace Domain\Llm;

use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Models\Llm;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Services\SharedHttpHandler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use Support\Http\ProviderRetry;
use Throwable;

/**
 * Base for the OpenAI-compatible chat providers: streaming through Guzzle,
 * non-streaming through the Laravel HTTP client (with token usage tracking),
 * and hooks to adjust the request body per provider. The key and extra
 * headers come from the provider row an admin set up, the address from the
 * provider's adapter. New
 * non-streaming callers use LlmChat, which wraps completion() in typed
 * request and response objects.
 */
abstract class AbstractLlmProvider implements ChatCompletionProvider
{
    /**
     * The request key for how hard the model thinks: a value the model takes,
     * chosen by the app from the model's facts ({@see Llm::effortValue()}).
     * Each provider puts it in its own field in preparePayload().
     */
    public const string REASONING_EFFORT = 'reasoning_effort';

    /**
     * A setting no provider receives: the tool loop sets it when the turn
     * shows the model's thinking, so a provider that must ask for the
     * thinking on its own (OpenAI's summaries) asks only then.
     */
    public const string SHOW_REASONING = 'show_reasoning';

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

        $json = $this->preparePayload($this->withFileParts($json));
        unset($json[self::SHOW_REASONING]);

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
        $data = $this->preparePayload($this->withFileParts($data));

        // Background tasks (summaries, enhancement, translations) use this;
        // a moment's rate limit is waited out rather than failing the task.
        return ProviderRetry::on($this->client())
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $data)
            ->throw();
    }

    /**
     * Send a completion request on the shared handler ({@see SharedHttpHandler}),
     * so it runs at the same time as the request's other async AI calls. It
     * is sent at once, so the Spectra tags around this call apply to it.
     *
     * @param  array<string, mixed>  $data  The full request body
     * @return PromiseInterface resolving to Response
     */
    public function completionAsync(array $data): PromiseInterface
    {
        /** @var LazyPromise $pending */
        $pending = $this->client()
            ->setHandler(app(SharedHttpHandler::class)->handler())
            ->async()
            ->timeout(600)
            ->post($this->getCompletionEndpoint(), $this->preparePayload($this->withFileParts($data)));

        // Laravel resolves with an error response or the connection error
        // itself; reject on both, as completion() throws.
        return $pending->buildPromise()->then(static function (Response|Throwable $response): Response {
            if ($response instanceof Throwable) {
                throw $response;
            }

            return $response->throw();
        });
    }

    /**
     * Write each PDF part (`document_url`) of the messages as a Chat
     * Completions `file` part, the form OpenAI, OpenRouter and Gemini take.
     * A provider with its own form for files overrides this.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withFileParts(array $payload): array
    {
        if (! is_array($payload['messages'] ?? null)) {
            return $payload;
        }

        $payload['messages'] = array_map(static function (mixed $message): mixed {
            if (! is_array($message) || ! is_array($message['content'] ?? null)) {
                return $message;
            }

            $message['content'] = array_map(static fn (mixed $part): mixed => is_array($part) && ($part['type'] ?? null) === 'document_url'
                ? ['type' => 'file', 'file' => [
                    'filename' => (string) ($part['document_name'] ?? 'document.pdf'),
                    'file_data' => (string) ($part['document_url']['url'] ?? $part['document_url'] ?? ''),
                ]]
                : $part, $message['content']);

            return $message;
        }, $payload['messages']);

        return $payload;
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
