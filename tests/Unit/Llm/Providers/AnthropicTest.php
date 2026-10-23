<?php

declare(strict_types=1);

use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Anthropic;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Claude through Anthropic's own Messages API: the key in its own header,
 * streamed turns and single calls both sent to `/messages` in Messages form
 * and read back as Chat Completions, and the older models, which refuse
 * adaptive thinking, asked again on a thinking budget.
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
    $history = [];
    $stack = HandlerStack::create(new MockHandler($replies));
    $stack->push(Middleware::history($history));
    app()->instance(Client::class, new Client(['handler' => $stack]));

    anthropicClient()->stream('claude-sonnet-4-5', [['role' => 'user', 'content' => 'Hi']], $settings);

    return array_map(static fn (array $entry): array => json_decode((string) $entry['request']->getBody(), true), $history);
}

function anthropicRefusal(): Response
{
    return new Response(400, [], (string) json_encode(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => '"thinking.type.adaptive" is not supported for this model.']]));
}

it('calls with its own key header and API version, not a bearer key', function (): void {
    expect(anthropicClient()->headers())->toMatchArray(['x-api-key' => 'sk-ant-test', 'anthropic-version' => '2023-06-01'])
        ->and(anthropicClient()->headers())->not->toHaveKey('Authorization')
        ->and(anthropicClient()->getCompletionEndpoint())->toBe('https://api.anthropic.test/v1/messages');
});

it('streams a turn to /messages in Messages form', function (): void {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([new Response(200, [], "event: message_stop\ndata: {\"type\":\"message_stop\"}\n\n")]));
    $stack->push(Middleware::history($history));
    app()->instance(Client::class, new Client(['handler' => $stack]));

    $reply = anthropicClient()->stream('claude-sonnet-5-5', [['role' => 'system', 'content' => 'Be brief.'], ['role' => 'user', 'content' => 'Hi']], [
        'reasoning_effort' => 'medium',
        Anthropic::SHOW_REASONING => true,
    ]);

    $request = $history[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect((string) $request->getUri())->toBe('https://api.anthropic.test/v1/messages')
        ->and($request->getHeaderLine('x-api-key'))->toBe('sk-ant-test')
        ->and($body)->toMatchArray(['model' => 'claude-sonnet-5-5', 'stream' => true, 'system' => 'Be brief.'])
        ->and($body['thinking'])->toBe(['type' => 'adaptive', 'display' => 'summarized'])
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hi']]]])
        ->and($body)->not->toHaveKeys(['show_reasoning', 'stream_options'])
        ->and($reply->getBody()->getContents())->toBe("data: [DONE]\n\n");
});

it('streams again on a thinking budget when the model refuses adaptive thinking, and remembers it', function (): void {
    $bodies = anthropicStreamBodies([anthropicRefusal(), new Response(200, [], '')], ['reasoning_effort' => 'low']);

    expect($bodies)->toHaveCount(2)
        ->and($bodies[0]['thinking']['type'])->toBe('adaptive')
        ->and($bodies[1]['thinking'])->toBe(['type' => 'enabled', 'budget_tokens' => 2048]);

    $next = anthropicStreamBodies([new Response(200, [], '')], ['reasoning_effort' => 'low']);

    expect($next)->toHaveCount(1)
        ->and($next[0]['thinking']['type'])->toBe('enabled');
});

it('passes on any other refusal', function (): void {
    expect(fn (): array => anthropicStreamBodies([new Response(400, [], '{"error":{"message":"max_tokens too large"}}')], ['reasoning_effort' => 'low']))
        ->toThrow(ClientException::class);
});

it('reads a single call\'s reply as a Chat Completions reply', function (): void {
    Http::fake(['*' => Http::response([
        'id' => 'msg_1', 'type' => 'message', 'model' => 'claude-haiku-5-5',
        'content' => [['type' => 'text', 'text' => '{"ok":true}']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 12, 'output_tokens' => 4],
    ])]);

    $response = anthropicClient()->completion([
        'model' => 'claude-haiku-5-5',
        'messages' => [['role' => 'system', 'content' => 'Judge.'], ['role' => 'user', 'content' => 'Is it fine?']],
        'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'verdict', 'schema' => ['type' => 'object']]],
    ]);

    expect($response->json('choices.0.message.content'))->toBe('{"ok":true}')
        ->and($response->json('usage.prompt_tokens'))->toBe(12);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.test/v1/messages'
        && $request['system'] === 'Judge.'
        && $request['output_config'] === ['format' => ['type' => 'json_schema', 'schema' => ['type' => 'object', 'additionalProperties' => false]]]
        && $request['max_tokens'] === 16384);
});

it('asks a single call again on a thinking budget, and fails on other errors', function (): void {
    Http::fakeSequence()
        ->push(['error' => ['message' => 'adaptive thinking is not supported']], 400)
        ->push(['content' => [['type' => 'text', 'text' => 'ok']], 'stop_reason' => 'end_turn'])
        ->push(['error' => ['message' => 'overloaded']], 529);

    expect(anthropicClient()->completion(['model' => 'claude-haiku-4-5', 'messages' => [], 'reasoning_effort' => 'medium'])->json('choices.0.message.content'))->toBe('ok')
        ->and(fn () => anthropicClient()->completion(['model' => 'claude-haiku-4-5', 'messages' => []]))->toThrow(RequestException::class);
});

it('reads an async call\'s reply as a Chat Completions reply', function (): void {
    Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'async ok']], 'stop_reason' => 'end_turn'])]);

    expect(anthropicClient()->completionAsync(['model' => 'claude-haiku-5-5', 'messages' => []])->wait()->json('choices.0.message.content'))->toBe('async ok');
});
