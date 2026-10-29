<?php

declare(strict_types=1);

use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Anthropic;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Support\Llm\GuzzleRecorder;

/**
 * Claude through Anthropic's own Messages API: the key in its own header,
 * single calls read back as Chat Completions, and the older models, which
 * refuse adaptive thinking, asked again on a thinking budget, in streamed
 * turns and single calls alike; any other refusal is passed on.
 */
function anthropicClient(): Anthropic
{
    return new Anthropic(LlmProvider::factory()->make(['id' => 7]), 'https://api.anthropic.test/v1', 'sk-ant-test');
}

/**
 * Stream one turn against the given replies and return the request bodies sent.
 *
 * @param  list<Response>  $replies
 * @return list<array<string, mixed>>
 */
function anthropicStreamBodies(array $replies, array $settings = []): array
{
    $recorder = GuzzleRecorder::answering(...$replies);

    anthropicClient()->stream('claude-sonnet-4-5', [['role' => 'user', 'content' => 'Hi']], $settings);

    return $recorder->bodies();
}

function anthropicRefusal(): Response
{
    return new Response(400, [], (string) json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => '"thinking.type.adaptive" is not supported for this model.']]));
}

it('streams again on a thinking budget when the model refuses adaptive thinking, remembers it, and passes on any other refusal', function (): void {
    $bodies = anthropicStreamBodies([anthropicRefusal(), new Response(200, [], '')], ['reasoning_effort' => 'low']);
    $next = anthropicStreamBodies([new Response(200, [], '')], ['reasoning_effort' => 'low']);

    expect($bodies)->toHaveCount(2)
        ->and($bodies[0]['thinking']['type'])->toBe('adaptive')
        ->and($bodies[1]['thinking'])->toBe(['type' => 'enabled', 'budget_tokens' => 2048])
        ->and($next)->toHaveCount(1)
        ->and($next[0]['thinking']['type'])->toBe('enabled')
        ->and(fn (): array => anthropicStreamBodies([new Response(400, [], '{"error":{"message":"max_tokens too large"}}')], ['reasoning_effort' => 'low']))
        ->toThrow(ClientException::class);
});

it('reads a single call\'s reply as a Chat Completions reply with its own key header, asks again on a thinking budget and fails on other errors', function (): void {
    Http::fakeSequence()
        ->push([
            'id' => 'msg_1', 'type' => 'message', 'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => '{"ok":true}']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
        ])
        ->push(['error' => ['message' => 'adaptive thinking is not supported']], 400)
        ->push(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn'])
        ->push(['error' => ['message' => 'overloaded']], 529);

    $response = anthropicClient()->completion([
        'model' => 'claude-haiku-5-5',
        'messages' => [['role' => 'system', 'content' => 'Judge.'], ['role' => 'user', 'content' => 'Is it fine?']],
        'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'verdict', 'schema' => ['type' => 'object']]],
    ]);
    $retried = anthropicClient()->completion(['model' => 'claude-haiku-4-5', 'messages' => [], 'reasoning_effort' => 'medium']);

    expect($response->json('choices.0.message.content'))->toBe('{"ok":true}')
        ->and($response->json('usage.prompt_tokens'))->toBe(12)
        ->and($retried->json('choices.0.message.content'))->toBe('ok')
        ->and(fn (): Illuminate\Http\Client\Response => anthropicClient()->completion(['model' => 'claude-haiku-4-5', 'messages' => []]))->toThrow(RequestException::class)
        ->and(anthropicClient()->headers())->toMatchArray(['x-api-key' => 'sk-ant-test', 'anthropic-version' => '2023-06-01'])
        ->and(anthropicClient()->headers())->not->toHaveKey('Authorization');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.test/v1/messages'
        && ($request->data()['system'] ?? null) === 'Judge.'
        && ($request->data()['output_config'] ?? null) === ['format' => ['type' => 'json_schema', 'schema' => ['type' => 'object', 'additionalProperties' => false]]]
        && ($request->data()['max_tokens'] ?? null) === 16384);
});
