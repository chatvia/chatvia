<?php

declare(strict_types=1);

use Tests\Support\Conversation\OpenAiStream;

/**
 * The transformer reads an OpenAI-compatible SSE body: text becomes deltas,
 * tool calls are collected on the context, and a model that writes a tool
 * call as JSON text gets it read as a real call, but only for a tool it was
 * offered. The model's thinking is OpenAiCompatibleTransformerReasoningTest's.
 */
it('streams plain text content as deltas', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'Hello ']]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => 'world!']]]]),
        "data: [DONE]\n",
    ]);

    expect(OpenAiStream::texts($events))->toBe(['Hello ', 'world!'])
        ->and($context->streamedMessage)->toBe('Hello world!')
        ->and($context->pendingToolCalls)->toBe([]);
});

it('accumulates structured tool calls from the delta stream', function (): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_xyz',
            'function' => ['name' => 'mcp__charts__render-chart-tool', 'arguments' => '{"x":'],
        ]]]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'function' => ['arguments' => '1}'],
        ]]]]]]),
        "data: [DONE]\n",
    ]);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__charts__render-chart-tool')
        ->and($context->pendingToolCalls[0]['arguments'])->toBe('{"x":1}');
});

it('reads a single JSON object in the content as a tool call', function (): void {
    $payload = json_encode([
        'name' => 'mcp__charts__render-chart-tool',
        'parameters' => ['title' => 'Numbers 1-10'],
        'summary' => 'A chart',
    ]);

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__charts__render-chart-tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->streamedMessage)->toBe('')
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__charts__render-chart-tool')
        ->and(json_decode($context->pendingToolCalls[0]['arguments'], true))->toBe(['title' => 'Numbers 1-10']);
});

it('reads a tool call from JSON wrapped in a markdown fence', function (): void {
    $payload = "```json\n".json_encode([
        'name' => 'mcp__currency__exchange-rate-tool',
        'parameters' => ['from' => 'USD', 'to' => 'DKK', 'amount' => 1000],
    ])."\n```";

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__currency__exchange-rate-tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__currency__exchange-rate-tool');
});

it('reads several tool calls from a JSON array in the content', function (): void {
    $payload = json_encode([
        ['name' => 'mcp__currency__exchange-rate-tool', 'parameters' => ['from' => 'USD', 'to' => 'DKK', 'amount' => 1000]],
        ['name' => 'mcp__charts__render-chart-tool', 'parameters' => ['title' => 'USD to DKK']],
    ]);

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__currency__exchange-rate-tool', 'mcp__charts__render-chart-tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and(array_column($context->pendingToolCalls, 'name'))->toBe(['mcp__currency__exchange-rate-tool', 'mcp__charts__render-chart-tool']);
});

it('reads tool calls from a tool_calls wrapper in the content', function (): void {
    $payload = json_encode([
        'tool_calls' => [[
            'id' => 'call_abc',
            'type' => 'function',
            'function' => [
                'name' => 'mcp__charts__render-chart-tool',
                'arguments' => json_encode(['title' => 'X']),
            ],
        ]],
    ]);

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__charts__render-chart-tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__charts__render-chart-tool')
        ->and(json_decode($context->pendingToolCalls[0]['arguments'], true))->toBe(['title' => 'X']);
});

it('streams JSON-looking content as text when it is no call of an offered tool', function (string $content, array $offered): void {
    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $content]]]]),
        "data: [DONE]\n",
    ], $offered);

    expect(OpenAiStream::texts($events))->toBe([$content])
        ->and($context->streamedMessage)->toBe($content)
        ->and($context->pendingToolCalls)->toBe([]);
})->with([
    // So the visitor sees something.
    'JSON it cannot parse' => ['{not really json', ['lookup_order']],
    // Without tools on the turn, a reply that happens to be JSON with a
    // `name` key is an answer, not a tool call.
    'a JSON answer on a turn without tools' => [json_encode(['name' => 'delete_everything', 'parameters' => ['confirm' => true]]), []],
    'JSON naming no offered tool' => [json_encode(['name' => 'Alice', 'arguments' => ['age' => 30]]), ['lookup_order']],
]);

it('ignores a JSON tool call in the content when real tool calls arrive', function (): void {
    $payload = json_encode(['name' => 'should_be_ignored', 'parameters' => []]);

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_real',
            'function' => ['name' => 'real_tool', 'arguments' => '{}'],
        ]]]]]]),
        "data: [DONE]\n",
    ], ['real_tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('real_tool');
});

it('finishes the content when the stream ends without a [DONE] line', function (): void {
    $payload = json_encode(['name' => 'mcp__charts__render-chart-tool', 'parameters' => []]);

    [$events, $context] = OpenAiStream::transform([
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => $payload]]]]),
    ], ['mcp__charts__render-chart-tool']);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1);
});
