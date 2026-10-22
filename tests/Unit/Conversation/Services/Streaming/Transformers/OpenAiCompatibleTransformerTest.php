<?php

declare(strict_types=1);

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * The transformer reads an OpenAI-compatible SSE body: text becomes deltas,
 * tool calls are collected on the context, and a model that writes a tool
 * call as JSON text gets it read as a real call, but only for a tool it was
 * offered.
 */

/**
 * A context with only what the transformer reads, so no turn has to be built.
 *
 * @param  list<string>  $offeredToolNames
 */
function oactContext(array $offeredToolNames): ChatContext
{
    $reflection = new ReflectionClass(ChatContext::class);
    $context = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('sources')->setValue($context, collect());
    $reflection->getProperty('assistantMessageId')->setValue($context, 'asst-test');
    $context->offeredToolNames = $offeredToolNames;

    return $context;
}

/**
 * Run the transformer over the given SSE lines.
 *
 * @param  list<string>  $bodyChunks
 * @param  list<string>  $offeredToolNames  tools offered on the turn (none by default)
 * @return array{0: list<StreamEvent>, 1: ChatContext}
 */
function oactTransform(array $bodyChunks, array $offeredToolNames = []): array
{
    $context = oactContext($offeredToolNames);
    $response = new Response(200, [], Utils::streamFor(implode('', $bodyChunks)));

    return [iterator_to_array(new OpenAiCompatibleTransformer()->transform($response, $context), false), $context];
}

/** @param  array<string, mixed>  $payload */
function oactChunk(array $payload): string
{
    return 'data: '.json_encode($payload)."\n";
}

/**
 * @param  list<StreamEvent>  $events
 * @return list<string>
 */
function oactDeltaTexts(array $events): array
{
    return array_values(array_map(
        static fn (StreamEvent $event): string => $event->data['text'],
        array_filter($events, static fn (StreamEvent $event): bool => $event->type === EventType::Delta),
    ));
}

it('streams plain text content as deltas', function (): void {
    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => 'Hello ']]]]),
        oactChunk(['choices' => [['delta' => ['content' => 'world!']]]]),
        "data: [DONE]\n",
    ]);

    expect(oactDeltaTexts($events))->toBe(['Hello ', 'world!'])
        ->and($context->streamedMessage)->toBe('Hello world!')
        ->and($context->pendingToolCalls)->toBe([]);
});

it('accumulates structured tool calls from the delta stream', function (): void {
    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_xyz',
            'function' => ['name' => 'mcp__charts__render-chart-tool', 'arguments' => '{"x":'],
        ]]]]]]),
        oactChunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'function' => ['arguments' => '1}'],
        ]]]]]]),
        "data: [DONE]\n",
    ]);

    expect(oactDeltaTexts($events))->toBe([])
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

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__charts__render-chart-tool']);

    expect(oactDeltaTexts($events))->toBe([])
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

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__currency__exchange-rate-tool']);

    expect(oactDeltaTexts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__currency__exchange-rate-tool');
});

it('reads several tool calls from a JSON array in the content', function (): void {
    $payload = json_encode([
        ['name' => 'mcp__currency__exchange-rate-tool', 'parameters' => ['from' => 'USD', 'to' => 'DKK', 'amount' => 1000]],
        ['name' => 'mcp__charts__render-chart-tool', 'parameters' => ['title' => 'USD to DKK']],
    ]);

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__currency__exchange-rate-tool', 'mcp__charts__render-chart-tool']);

    expect(oactDeltaTexts($events))->toBe([])
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

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['mcp__charts__render-chart-tool']);

    expect(oactDeltaTexts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('mcp__charts__render-chart-tool')
        ->and(json_decode($context->pendingToolCalls[0]['arguments'], true))->toBe(['title' => 'X']);
});

it('sends JSON-looking content it cannot parse as text, so the visitor sees something', function (): void {
    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => '{not really json']]]]),
        "data: [DONE]\n",
    ], ['lookup_order']);

    expect(oactDeltaTexts($events))->toBe(['{not really json'])
        ->and($context->streamedMessage)->toBe('{not really json')
        ->and($context->pendingToolCalls)->toBe([]);
});

it('ignores a JSON tool call in the content when real tool calls arrive', function (): void {
    $payload = json_encode(['name' => 'should_be_ignored', 'parameters' => []]);

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        oactChunk(['choices' => [['delta' => ['tool_calls' => [[
            'index' => 0,
            'id' => 'call_real',
            'function' => ['name' => 'real_tool', 'arguments' => '{}'],
        ]]]]]]),
        "data: [DONE]\n",
    ], ['real_tool']);

    expect(oactDeltaTexts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1)
        ->and($context->pendingToolCalls[0]['name'])->toBe('real_tool');
});

it('finishes the content when the stream ends without a [DONE] line', function (): void {
    $payload = json_encode(['name' => 'mcp__charts__render-chart-tool', 'parameters' => []]);

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
    ], ['mcp__charts__render-chart-tool']);

    expect(oactDeltaTexts($events))->toBe([])
        ->and($context->pendingToolCalls)->toHaveCount(1);
});

it('streams a JSON answer as text when no tools were offered', function (): void {
    // Without tools on the turn, a reply that happens to be JSON with a
    // `name` key is an answer, not a tool call.
    $payload = json_encode(['name' => 'delete_everything', 'parameters' => ['confirm' => true]]);

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ]);

    expect(oactDeltaTexts($events))->toBe([$payload])
        ->and($context->streamedMessage)->toBe($payload)
        ->and($context->pendingToolCalls)->toBe([]);
});

it('streams JSON as text when it names no offered tool', function (): void {
    $payload = json_encode(['name' => 'Alice', 'arguments' => ['age' => 30]]);

    [$events, $context] = oactTransform([
        oactChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        "data: [DONE]\n",
    ], ['lookup_order']);

    expect(oactDeltaTexts($events))->toBe([$payload])
        ->and($context->pendingToolCalls)->toBe([]);
});

/**
 * Run the transformer with the turn showing (or not) the model's thinking.
 *
 * @param  list<string>  $bodyChunks
 * @return array{0: list<StreamEvent>, 1: ChatContext}
 */
function oactTransformReasoning(array $bodyChunks, bool $shows = true): array
{
    $context = oactContext([]);
    $context->showsReasoning = $shows;
    $response = new Response(200, [], Utils::streamFor(implode('', $bodyChunks)));

    return [iterator_to_array(new OpenAiCompatibleTransformer()->transform($response, $context), false), $context];
}

/**
 * @param  list<StreamEvent>  $events
 */
function oactReasoningText(array $events): string
{
    return implode('', array_map(
        static fn (StreamEvent $event): string => $event->data['text'],
        array_filter($events, static fn (StreamEvent $event): bool => $event->type === EventType::Reasoning),
    ));
}

it('sends the thinking in its own field as reasoning, never as the answer', function (string $field): void {
    [$events, $context] = oactTransformReasoning([
        oactChunk(['choices' => [['delta' => [$field => 'Let me ']]]]),
        oactChunk(['choices' => [['delta' => [$field => 'think.']]]]),
        oactChunk(['choices' => [['delta' => ['content' => 'The answer.']]]]),
        "data: [DONE]\n",
    ]);

    expect(oactReasoningText($events))->toBe('Let me think.')
        ->and(oactDeltaTexts($events))->toBe(['The answer.'])
        ->and($context->streamedMessage)->toBe('The answer.');
})->with(['reasoning_content', 'reasoning']);

it('reads OpenRouter reasoning_details when the delta has no reasoning text', function (): void {
    [$events] = oactTransformReasoning([
        oactChunk(['choices' => [['delta' => ['reasoning_details' => [['type' => 'reasoning.summary', 'summary' => 'A summary.']]]]]]),
        "data: [DONE]\n",
    ]);

    expect(oactReasoningText($events))->toBe('A summary.');
});

it('takes a leading inline think block out of the answer, even split over chunks', function (): void {
    [$events, $context] = oactTransformReasoning([
        oactChunk(['choices' => [['delta' => ['content' => "\n<thi"]]]]),
        oactChunk(['choices' => [['delta' => ['content' => 'nk>Weighing it']]]]),
        oactChunk(['choices' => [['delta' => ['content' => ' up.</th']]]]),
        oactChunk(['choices' => [['delta' => ['content' => "ink>\n\nIt is 42."]]]]),
        "data: [DONE]\n",
    ]);

    expect(oactReasoningText($events))->toBe('Weighing it up.')
        ->and(implode('', oactDeltaTexts($events)))->toBe('It is 42.')
        ->and($context->streamedMessage)->toBe('It is 42.');
});

it('keeps an answer that only starts like a think tag', function (): void {
    [$events, $context] = oactTransformReasoning([
        oactChunk(['choices' => [['delta' => ['content' => '<th']]]]),
        oactChunk(['choices' => [['delta' => ['content' => 'ree> is not a tag']]]]),
        "data: [DONE]\n",
    ]);

    expect(oactReasoningText($events))->toBe('')
        ->and($context->streamedMessage)->toBe('<three> is not a tag');
});

it('drops the thinking when the turn does not show it', function (): void {
    [$events, $context] = oactTransformReasoning([
        oactChunk(['choices' => [['delta' => ['reasoning_content' => 'Hidden.']]]]),
        oactChunk(['choices' => [['delta' => ['content' => '<think>Also hidden.</think>Shown.']]]]),
        "data: [DONE]\n",
    ], shows: false);

    expect(array_filter($events, static fn (StreamEvent $event): bool => $event->type === EventType::Reasoning))->toBe([])
        ->and($context->streamedMessage)->toBe('Shown.');
});
