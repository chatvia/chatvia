<?php

declare(strict_types=1);

use Tests\Support\Conversation\OpenAiStream;

/**
 * The transformer reads an OpenAI-compatible SSE body: text becomes deltas,
 * tool calls are collected on the context, and a model that writes a tool
 * call as JSON text gets it read as a real call, but only for a tool it was
 * offered. The model's thinking is OpenAiCompatibleTransformerReasoningTest's.
 */
it('streams text as deltas, also JSON that is no call of an offered tool', function (array $contents, array $offered): void {
    [$events, $context] = OpenAiStream::transform([
        ...array_map(static fn (string $content): string => OpenAiStream::chunk(['choices' => [['delta' => ['content' => $content]]]]), $contents),
        "data: [DONE]\n",
    ], $offered);

    expect(OpenAiStream::texts($events))->toBe($contents)
        ->and($context->streamedMessage)->toBe(implode('', $contents))
        ->and($context->pendingToolCalls)->toBe([]);
})->with([
    'plain text' => [['Hello ', 'world!'], []],
    'JSON it cannot parse' => [['{not really json'], ['lookup_order']],
    'a JSON answer on a turn without tools' => [[json_encode(['name' => 'delete_everything', 'parameters' => ['confirm' => true]])], []],
    'JSON naming no offered tool' => [[json_encode(['name' => 'Alice', 'arguments' => ['age' => 30]])], ['lookup_order']],
]);

it('collects tool calls from the delta stream, or from JSON text naming an offered tool', function (array $chunks, array $offered, array $calls): void {
    [$events, $context] = OpenAiStream::transform($chunks, $offered);

    expect(OpenAiStream::texts($events))->toBe([])
        ->and(collect($context->pendingToolCalls)->map(static fn (array $call): array => [$call['name'], json_decode($call['arguments'], true, 512, JSON_THROW_ON_ERROR)])->all())->toBe($calls);
})->with([
    'structured deltas' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_xyz', 'function' => ['name' => 'render', 'arguments' => '{"x":']]]]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'function' => ['arguments' => '1}']]]]]]]),
        "data: [DONE]\n",
    ], [], [['render', ['x' => 1]]]],
    'a single JSON object' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => json_encode(['name' => 'render', 'parameters' => ['title' => 'Numbers'], 'summary' => 'A chart'])]]]]),
        "data: [DONE]\n",
    ], ['render'], [['render', ['title' => 'Numbers']]]],
    'JSON in a markdown fence' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => "```json\n".json_encode(['name' => 'rate', 'parameters' => ['from' => 'USD']])."\n```"]]]]),
        "data: [DONE]\n",
    ], ['rate'], [['rate', ['from' => 'USD']]]],
    'a JSON array of calls' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => json_encode([['name' => 'rate', 'parameters' => ['from' => 'USD']], ['name' => 'render', 'parameters' => ['title' => 'X']]])]]]]),
        "data: [DONE]\n",
    ], ['rate', 'render'], [['rate', ['from' => 'USD']], ['render', ['title' => 'X']]]],
    'a tool_calls wrapper' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => json_encode(['tool_calls' => [['id' => 'call_abc', 'type' => 'function', 'function' => ['name' => 'render', 'arguments' => json_encode(['title' => 'X'])]]]])]]]]),
        "data: [DONE]\n",
    ], ['render'], [['render', ['title' => 'X']]]],
    'real calls win over JSON text' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => json_encode(['name' => 'should_be_ignored', 'parameters' => []])]]]]),
        OpenAiStream::chunk(['choices' => [['delta' => ['tool_calls' => [['index' => 0, 'id' => 'call_real', 'function' => ['name' => 'real_tool', 'arguments' => '{}']]]]]]]),
        "data: [DONE]\n",
    ], ['real_tool'], [['real_tool', []]]],
    'a stream that ends without [DONE]' => [[
        OpenAiStream::chunk(['choices' => [['delta' => ['content' => json_encode(['name' => 'render', 'parameters' => ['title' => 'X']])]]]]),
    ], ['render'], [['render', ['title' => 'X']]]],
]);
