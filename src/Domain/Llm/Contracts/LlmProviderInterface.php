<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * An OpenAI-compatible chat-completion provider. The chat engine streams
 * through stream(); other callers send one request with completion(),
 * preferably through LlmChat / StructuredCompletion, which take and return
 * typed objects instead of raw arrays.
 */
interface LlmProviderInterface
{
    /**
     * Stream a completion request.
     *
     * @param  string  $model  The model identifier
     * @param  list<array<string, mixed>>  $messages  OpenAI-style chat messages (`role`, `content`, ...)
     * @param  array<string, mixed>  $settings  Extra request body keys (temperature, tools, ...)
     * @return ResponseInterface The streaming response
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface;

    public function client(): PendingRequest;

    /**
     * Execute a completion request.
     *
     * @param  array<string, mixed>  $data  The full request body
     * @return Response The response
     */
    public function completion(array $data);

    /**
     * Send a completion request without waiting for it, so other calls can
     * run at the same time. The promise resolves to the response, or is
     * rejected on an error status or a failed connection, as completion()
     * would throw.
     *
     * @param  array<string, mixed>  $data  The full request body
     * @return PromiseInterface resolving to Response
     */
    public function completionAsync(array $data): PromiseInterface;

    /**
     * Get the headers for API requests.
     *
     * @return array<string, string> The headers array
     */
    public function headers(): array;

    /**
     * Look up one generation after the fact, for its token counts when the
     * stream reported none. Returns null if the provider has no such lookup.
     *
     * @param  string  $generationId  The generation ID
     * @return array<string, mixed>|null The provider's generation record (`tokens_prompt`, `tokens_completion`, ...) or null if not supported
     */
    public function getGeneration(string $generationId): ?array;

    /**
     * Get the completion endpoint URL.
     *
     * @return string The completion endpoint
     */
    public function getCompletionEndpoint(): string;

    /**
     * Check if the provider can look up a generation by its id.
     *
     * @return bool True if getGeneration() is supported
     */
    public function supportsGenerationLookup(): bool;
}
