<?php

use Domain\Llm\Providers\Groq;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;

/**
 * Each provider adjusts the request body through its own preparePayload() /
 * prepareStreamPayload() hook: Mistral drops `top_k`, OpenRouter asks for the
 * fastest host on streams, Scaleway turns Qwen3 reasoning off. A provider
 * never receives another provider's fields.
 */
beforeEach(function (): void {
    config([
        'services.mistral.api_key' => 'k',
        'services.openrouter.api_key' => 'k',
        'services.groq.api_key' => 'k',
        'services.scalewayai.api_key' => 'k',
    ]);
});

/**
 * Stream one turn through the provider and return the JSON body it sent.
 *
 * @param  class-string  $provider
 * @return array<string, mixed>
 */
function hookStreamBody(string $provider): array
{
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], "data: [DONE]\n\n")]));
    $stack->push(Middleware::history($history));
    app()->instance(Client::class, new Client(['handler' => $stack]));

    app($provider)->stream('qwen3-model', [['role' => 'user', 'content' => 'hi']], ['top_k' => 40]);

    return json_decode((string) $history[0]['request']->getBody(), true);
}

/**
 * @param  class-string  $provider
 * @return array<string, mixed>
 */
function hookCompletionBody(string $provider): array
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    app($provider)->completion(['model' => 'qwen3-model', 'messages' => [], 'top_k' => 40]);

    $body = null;
    Http::assertSent(function ($request) use (&$body): bool {
        $body = $request->data();

        return true;
    });

    return $body;
}

it('drops top_k for Mistral on streams and completions', function (): void {
    expect(hookStreamBody(Mistral::class))->not->toHaveKey('top_k')
        ->and(hookCompletionBody(Mistral::class))->not->toHaveKey('top_k');
});

it('asks OpenRouter for the fastest host on streams only', function (): void {
    expect(hookStreamBody(OpenRouter::class)['provider'] ?? null)->toBe(['sort' => 'throughput'])
        ->and(hookCompletionBody(OpenRouter::class))->not->toHaveKey('provider');
});

it('turns Qwen3 reasoning off for Scaleway streams', function (): void {
    expect(hookStreamBody(Scaleway::class)['reasoning_effort'] ?? null)->toBe('none');
});

it('leaves the other providers\' bodies alone', function (string $provider): void {
    $body = hookStreamBody($provider);

    expect($body['top_k'])->toBe(40)
        ->and($body)->not->toHaveKey('reasoning_effort')
        ->and($body['stream_options'])->toBe(['include_usage' => true]);
})->with([
    'groq' => Groq::class,
    'openrouter' => OpenRouter::class,
]);

it('asks every provider for token usage on streams', function (string $provider): void {
    expect(hookStreamBody($provider)['stream_options']['include_usage'] ?? null)->toBeTrue();
})->with([Groq::class, Mistral::class, OpenRouter::class, Scaleway::class]);
