<?php

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenAi;
use Domain\Llm\Providers\OpenAiCompatible;
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
 * prepareStreamPayload() hook: Mistral and OpenAI drop `top_k`, OpenAI fits
 * its reasoning models, OpenRouter asks for the
 * fastest host on streams, Scaleway turns Qwen3 reasoning off. A provider
 * never receives another provider's fields.
 */
/**
 * The client of the given class, for a test provider.
 *
 * @param  class-string<LlmProviderInterface>  $provider
 */
function hookClient(string $provider): LlmProviderInterface
{
    return new $provider(LlmProvider::factory()->make(), 'https://llm.test/v1');
}

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

    hookClient($provider)->stream('qwen3-model', [['role' => 'user', 'content' => 'hi']], ['top_k' => 40]);

    return json_decode((string) $history[0]['request']->getBody(), true);
}

/**
 * @param  class-string  $provider
 * @return array<string, mixed>
 */
function hookCompletionBody(string $provider): array
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    hookClient($provider)->completion(['model' => 'qwen3-model', 'messages' => [], 'top_k' => 40]);

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

it('drops top_k for OpenAI and sends max_tokens as max_completion_tokens', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    hookClient(OpenAi::class)->completion(['model' => 'gpt-4.1-mini', 'messages' => [], 'top_k' => 40, 'max_tokens' => 50, 'temperature' => 0.2]);

    Http::assertSent(fn ($request): bool => ! array_key_exists('top_k', $request->data())
        && ! array_key_exists('max_tokens', $request->data())
        && $request['max_completion_tokens'] === 50
        // Sampling goes as sent: the app fits it to the model's facts.
        && $request['temperature'] === 0.2);
    expect(hookStreamBody(OpenAi::class))->not->toHaveKey('top_k');
});

it('asks OpenRouter for the fastest host on streams only', function (): void {
    expect(hookStreamBody(OpenRouter::class)['provider'] ?? null)->toBe(['sort' => 'throughput'])
        ->and(hookCompletionBody(OpenRouter::class))->not->toHaveKey('provider');
});

it('leaves the other providers\' bodies alone', function (string $provider): void {
    $body = hookStreamBody($provider);

    expect($body['top_k'])->toBe(40)
        ->and($body)->not->toHaveKey('reasoning_effort')
        ->and($body['stream_options'])->toBe(['include_usage' => true]);
})->with([
    'openai-compatible' => OpenAiCompatible::class,
    'openrouter' => OpenRouter::class,
    'scaleway' => Scaleway::class,
]);

it('asks every provider for token usage on streams', function (string $provider): void {
    expect(hookStreamBody($provider)['stream_options']['include_usage'] ?? null)->toBeTrue();
})->with([OpenAiCompatible::class, Mistral::class, OpenRouter::class, Scaleway::class]);

/**
 * The body a provider sends for a completion with this reasoning effort.
 *
 * @param  class-string<LlmProviderInterface>  $provider
 * @return array<string, mixed>
 */
function hookReasoningBody(string $provider, string $model, string $effort): array
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    hookClient($provider)->completion(['model' => $model, 'messages' => [], 'reasoning_effort' => $effort]);

    $body = [];
    Http::assertSent(function ($request) use (&$body): bool {
        $body = $request->data();

        return true;
    });

    return $body;
}

it('turns the reasoning effort into each provider\'s own field', function (string $provider, string $model, string $effort, array $expected, array $absent): void {
    $body = hookReasoningBody($provider, $model, $effort);

    expect(array_intersect_key($body, $expected))->toBe($expected);

    foreach ($absent as $key) {
        expect($body)->not->toHaveKey($key);
    }
})->with([
    // The app already chose a value the model takes; providers only move it.
    'openai as given' => [OpenAi::class, 'gpt-6-luna', 'none', ['reasoning_effort' => 'none'], []],
    'openrouter object, thinking left out' => [OpenRouter::class, 'openai/gpt-6.1-sol', 'medium', ['reasoning' => ['effort' => 'medium', 'exclude' => true]], ['reasoning_effort']],
    'scaleway as given' => [Scaleway::class, 'qwen3.6-35b-a3b', 'none', ['reasoning_effort' => 'none'], []],
    'compatible as given' => [OpenAiCompatible::class, 'qwen3-8b', 'low', ['reasoning_effort' => 'low'], []],
    'mistral has none' => [Mistral::class, 'magistral-medium', 'high', [], ['reasoning_effort']],
]);

it('lets OpenRouter send the thinking only when the turn shows it', function (bool $show, array $reasoning): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], "data: [DONE]\n\n")]));
    $stack->push(Middleware::history($history));
    app()->instance(Client::class, new Client(['handler' => $stack]));

    hookClient(OpenRouter::class)->stream('deepseek/deepseek-r1', [['role' => 'user', 'content' => 'hi']], ['reasoning_effort' => 'high'] + ($show ? ['show_reasoning' => true] : []));
    $body = json_decode((string) $history[0]['request']->getBody(), true);

    expect($body['reasoning'])->toBe($reasoning)
        ->and($body)->not->toHaveKey('show_reasoning');
})->with([
    'shown' => [true, ['effort' => 'high']],
    'not shown' => [false, ['effort' => 'high', 'exclude' => true]],
]);
