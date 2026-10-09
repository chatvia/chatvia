<?php

declare(strict_types=1);

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
     * Get the headers for API requests.
     *
     * @return array<string, string> The headers array
     */
    public function headers(): array;

    /**
     * Get generation cost data.
     * Returns null if the provider doesn't support cost tracking.
     *
     * @param  string  $generationId  The generation ID
     * @return array<string, mixed>|null The provider's generation record (`tokens_prompt`, `tokens_completion`, `total_cost`, ...) or null if not supported
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
