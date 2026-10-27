<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\ChatCompletionProvider;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * An LLM client with scripted answers, so a test needs no HTTP fake for the
 * model. completion() answers with the given text, or fails like a provider
 * that is down when no text is given; stream() returns an empty response; the
 * generation lookup returns the given record. Every request is kept, so a test
 * can check what was sent. Pass it to LlmChat::using(), FakeLlmProviders or
 * bindFakeLlmProvider().
 */
final class CannedLlmProvider implements ChatCompletionProvider
{
    /** @var list<array<string, mixed>> the request body of every completion() call */
    public array $completions = [];

    /** @var list<array<string, mixed>> the settings of every stream() call */
    public array $streamSettings = [];

    /**
     * @param  array<string, mixed>|null  $generation
     */
    public function __construct(
        private readonly ?string $answer = null,
        private readonly bool $looksUpGenerations = false,
        private readonly ?array $generation = null,
    ) {}

    /** completion() answers with this assistant text. */
    public static function answering(string $answer): self
    {
        return new self(answer: $answer);
    }

    /** completion() throws, as when the provider cannot be reached. */
    public static function failing(): self
    {
        return new self;
    }

    /**
     * The provider can look up generations and reports this record for any one.
     *
     * @param  array<string, mixed>|null  $generation
     */
    public static function withGeneration(?array $generation): self
    {
        return new self(looksUpGenerations: true, generation: $generation);
    }

    public function stream(string $model, array $messages, array $settings = []): ResponseInterface
    {
        $this->streamSettings[] = $settings;

        return new PsrResponse;
    }

    public function client(): PendingRequest
    {
        throw new RuntimeException('The canned provider has no HTTP client.');
    }

    public function completion(array $data): Response
    {
        $this->completions[] = $data;

        if ($this->answer === null) {
            throw new RuntimeException('provider down');
        }

        $body = ['choices' => [['message' => ['content' => $this->answer]]]];

        return new Response(new PsrResponse(200, [], (string) json_encode($body)));
    }

    public function completionAsync(array $data): PromiseInterface
    {
        try {
            return Create::promiseFor($this->completion($data));
        } catch (RuntimeException $e) {
            return Create::rejectionFor($e);
        }
    }

    public function headers(): array
    {
        return [];
    }

    public function getGeneration(string $generationId): ?array
    {
        return $this->generation;
    }

    public function getCompletionEndpoint(): string
    {
        return '';
    }

    public function supportsGenerationLookup(): bool
    {
        return $this->looksUpGenerations;
    }
}
