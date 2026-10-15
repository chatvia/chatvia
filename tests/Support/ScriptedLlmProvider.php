<?php

declare(strict_types=1);

namespace Tests\Support;

use Domain\Llm\Contracts\LlmProviderInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * An LLM client that streams scripted OpenAI-compatible SSE bodies, one per
 * stream() call, so a chat turn runs end to end without a real model. The
 * tool loop calls stream() once per round, so body N is model round N; after
 * the last body it streams an empty answer. Bind it with bindFakeLlmProvider().
 */
final class ScriptedLlmProvider implements LlmProviderInterface
{
    /** @var list<string> */
    private array $bodies;

    public function __construct(string ...$bodies)
    {
        $this->bodies = array_values($bodies);
    }

    /** One round that streams these pieces of text as the answer. */
    public static function answering(string ...$pieces): self
    {
        $payloads = array_map(
            static fn (string $piece): array => ['choices' => [['delta' => ['content' => $piece]]]],
            $pieces,
        );

        if ($payloads !== []) {
            $payloads[0]['id'] = 'gen-scripted';
        }

        return new self(self::body(...$payloads));
    }

    /**
     * One SSE body as a provider streams it: each payload a `data:` chunk,
     * then `data: [DONE]`.
     *
     * @param  array<string, mixed>  ...$payloads
     */
    public static function body(array ...$payloads): string
    {
        $body = '';

        foreach ($payloads as $payload) {
            $body .= 'data: '.json_encode($payload)."\n\n";
        }

        return $body."data: [DONE]\n\n";
    }

    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        $body = array_shift($this->bodies) ?? "data: [DONE]\n\n";

        return new PsrResponse(200, ['Content-Type' => 'text/event-stream'], $body);
    }

    public function client(): PendingRequest
    {
        throw new RuntimeException('The scripted provider has no HTTP client.');
    }

    public function completion(array $data): never
    {
        throw new RuntimeException('The scripted provider only streams.');
    }

    public function headers(): array
    {
        return [];
    }

    public function getGenerationCost(string $generationId): ?array
    {
        return null;
    }

    public function getCompletionEndpoint(): string
    {
        return 'https://fake.test/v1/chat/completions';
    }

    public function supportsCostTracking(): bool
    {
        return false;
    }
}
