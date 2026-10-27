<?php

declare(strict_types=1);

use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Mistral;
use Domain\Llm\Providers\OpenAi;
use Domain\Llm\Providers\OpenAiCompatible;
use Domain\Llm\Providers\OpenRouter;
use Domain\Llm\Providers\Scaleway;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Tests\Support\Llm\GuzzleRecorder;

/**
 * Each provider adjusts the request body through its own preparePayload() /
 * prepareStreamPayload() hook: Mistral and OpenAI drop `top_k`, OpenAI renames
 * `max_tokens`, OpenRouter asks for the fastest host on streams, and each
 * provider writes the reasoning effort the app chose in its own field. A
 * provider never receives another provider's fields.
 */

/**
 * The client of the given class, for a test provider.
 *
 * @param  class-string<ChatCompletionProvider>  $provider
 */
function hookClient(string $provider): ChatCompletionProvider
{
    return new $provider(LlmProvider::factory()->make(), 'https://llm.test/v1');
}

/**
 * Stream one turn through the provider and return the JSON body it sent.
 *
 * @param  class-string<ChatCompletionProvider>  $provider
 * @return array<string, mixed>
 */
function hookStreamBody(string $provider): array
{
    $recorder = GuzzleRecorder::answering(new Response(200, [], "data: [DONE]\n\n"));

    hookClient($provider)->stream('qwen3-model', [['role' => 'user', 'content' => 'hi']], ['top_k' => 40]);

    return $recorder->body();
}

/**
 * Send one completion through the provider and return the JSON body it sent.
 *
 * @param  class-string<ChatCompletionProvider>  $provider
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function hookCompletionBody(string $provider, array $data = ['model' => 'qwen3-model', 'messages' => [], 'top_k' => 40]): array
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);

    hookClient($provider)->completion($data);

    return Http::recorded()->sole()[0]->data();
}

it('drops top_k for Mistral on streams and completions', function (): void {
    expect(hookStreamBody(Mistral::class))->not->toHaveKey('top_k')
        ->and(hookCompletionBody(Mistral::class))->not->toHaveKey('top_k');
});

it('drops top_k for OpenAI and sends max_tokens as max_completion_tokens', function (): void {
    $body = hookCompletionBody(OpenAi::class, ['model' => 'gpt-4.1-mini', 'messages' => [], 'top_k' => 40, 'max_tokens' => 50, 'temperature' => 0.2]);

    expect($body)->not->toHaveKeys(['top_k', 'max_tokens'])
        ->and($body['max_completion_tokens'])->toBe(50)
        // Sampling goes as sent: the app fits it to the model's facts.
        ->and($body['temperature'])->toBe(0.2)
        ->and(hookStreamBody(OpenAi::class))->not->toHaveKey('top_k');
});

it('asks OpenRouter for the fastest host on streams only', function (): void {
    expect(hookStreamBody(OpenRouter::class)['provider'] ?? null)->toBe(['sort' => 'throughput'])
        ->and(hookCompletionBody(OpenRouter::class))->not->toHaveKey('provider');
});

it('leaves the other providers\' bodies alone', function (string $provider): void {
    $body = hookStreamBody($provider);

    expect($body['top_k'])->toBe(40)
        ->and($body)->not->toHaveKey('reasoning_effort');
})->with([
    'openai-compatible' => OpenAiCompatible::class,
    'openrouter' => OpenRouter::class,
    'scaleway' => Scaleway::class,
]);

it('asks every provider for token usage on streams', function (string $provider): void {
    expect(hookStreamBody($provider)['stream_options']['include_usage'] ?? null)->toBeTrue();
})->with([OpenAiCompatible::class, Mistral::class, OpenRouter::class, Scaleway::class]);

it('turns the reasoning effort into each provider\'s own field', function (string $provider, string $model, string $effort, array $expected, array $absent): void {
    $body = hookCompletionBody($provider, ['model' => $model, 'messages' => [], 'reasoning_effort' => $effort]);

    expect(array_intersect_key($body, $expected))->toBe($expected)
        ->and(array_intersect_key($body, array_flip($absent)))->toBe([]);
})->with([
    // The app already chose a value the model takes; providers only move it.
    'openai as given' => [OpenAi::class, 'gpt-6-luna', 'none', ['reasoning_effort' => 'none'], []],
    'openrouter object, thinking left out' => [OpenRouter::class, 'openai/gpt-6.1-sol', 'medium', ['reasoning' => ['effort' => 'medium', 'exclude' => true]], ['reasoning_effort']],
    'scaleway as given' => [Scaleway::class, 'qwen3.6-35b-a3b', 'none', ['reasoning_effort' => 'none'], []],
    'compatible as given' => [OpenAiCompatible::class, 'qwen3-8b', 'low', ['reasoning_effort' => 'low'], []],
    'mistral has none' => [Mistral::class, 'magistral-medium', 'high', [], ['reasoning_effort']],
]);

/** A user turn with a PDF attached, as the attachment source writes it. */
function hookPdfTurn(): array
{
    return [['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Summarise'],
        ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,JVBERg==', 'document_name' => 'report.pdf'],
    ]]];
}

it('sends a PDF as a Chat Completions file part, streamed or not', function (string $provider): void {
    $file = ['type' => 'file', 'file' => ['filename' => 'report.pdf', 'file_data' => 'data:application/pdf;base64,JVBERg==']];

    $recorder = GuzzleRecorder::answering(new Response(200, [], "data: [DONE]\n\n"));
    hookClient($provider)->stream('model', hookPdfTurn());

    expect($recorder->body()['messages'][0]['content'][1])->toBe($file)
        ->and(hookCompletionBody($provider, ['model' => 'model', 'messages' => hookPdfTurn()])['messages'][0]['content'][1])->toBe($file);
})->with([OpenRouter::class, OpenAiCompatible::class]);

it('keeps a PDF as Mistral\'s own document part', function (): void {
    $recorder = GuzzleRecorder::answering(new Response(200, [], "data: [DONE]\n\n"));
    hookClient(Mistral::class)->stream('model', hookPdfTurn());

    expect($recorder->body()['messages'][0]['content'][1])->toBe(hookPdfTurn()[0]['content'][1]);
});
