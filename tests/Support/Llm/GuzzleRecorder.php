<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The Guzzle client the LLM providers stream with, answering with scripted
 * responses in turn and keeping every request it was sent. Streams bypass
 * Http::fake(), so this is how a test sees what a provider streamed.
 */
final class GuzzleRecorder
{
    /** @var list<array{request: RequestInterface, response: ?ResponseInterface}> */
    public array $history = [];

    /** Bind the client, answering each request with the next of $responses. */
    public static function answering(ResponseInterface ...$responses): self
    {
        $recorder = new self;
        $stack = HandlerStack::create(new MockHandler(array_values($responses)));
        $stack->push(Middleware::history($recorder->history));
        app()->instance(Client::class, new Client(['handler' => $stack]));

        return $recorder;
    }

    public function request(int $index = 0): RequestInterface
    {
        return $this->history[$index]['request'];
    }

    /** @return array<string, mixed> the JSON body of the request at $index */
    public function body(int $index = 0): array
    {
        return json_decode((string) $this->request($index)->getBody(), true);
    }

    /** @return list<array<string, mixed>> the JSON body of every request */
    public function bodies(): array
    {
        return array_map(fn (int $index): array => $this->body($index), array_keys($this->history));
    }
}
