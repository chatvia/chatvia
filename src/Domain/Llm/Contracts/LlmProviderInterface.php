<?php

namespace Domain\Llm\Contracts;

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
     * @param  array  $messages  The messages array
     * @param  array  $settings  Additional settings for the request
     * @return ResponseInterface The streaming response
     */
    public function stream(string $model, array $messages, array $settings = []): ResponseInterface;

    public function client(): PendingRequest;

    /**
     * Execute a completion request.
     *
     * @param  array  $data  The request data
     * @return Response The response
     */
    public function completion(array $data);

    /**
     * Get the headers for API requests.
     *
     * @return array The headers array
     */
    public function headers(): array;

    /**
     * Get generation cost data.
     * Returns null if the provider doesn't support cost tracking.
     *
     * @param  string  $generationId  The generation ID
     * @return array|null The generation data or null if not supported
     */
    public function getGenerationCost(string $generationId): ?array;

    /**
     * Get the completion endpoint URL.
     *
     * @return string The completion endpoint
     */
    public function getCompletionEndpoint(): string;

    /**
     * Check if the provider supports cost tracking.
     *
     * @return bool True if cost tracking is supported
     */
    public function supportsCostTracking(): bool;
}
