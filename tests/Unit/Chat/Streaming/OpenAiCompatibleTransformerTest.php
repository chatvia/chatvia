<?php

namespace Tests\Unit\Chat\Streaming;

use Domain\Chat\Services\ChatContext;
use Domain\Chat\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Domain\Chat\Services\Streaming\Sse\StreamEvent;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Tests\TestCase;

class OpenAiCompatibleTransformerTest extends TestCase
{
    public function test_streams_plain_text_content_as_deltas(): void
    {
        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => 'Hello ']]]]),
            $this->sseChunk(['choices' => [['delta' => ['content' => 'world!']]]]),
            "data: [DONE]\n",
        ], $context);

        $deltas = $this->deltaTexts($events);
        $this->assertSame(['Hello ', 'world!'], $deltas);
        $this->assertSame('Hello world!', $context->streamedMessage);
        $this->assertSame([], $context->pendingToolCalls);
    }

    public function test_accumulates_structured_tool_calls_from_delta_stream(): void
    {
        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_xyz',
                'function' => ['name' => 'mcp__charts__render-chart-tool', 'arguments' => '{"x":'],
            ]]]]]]),
            $this->sseChunk(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'function' => ['arguments' => '1}'],
            ]]]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(1, $context->pendingToolCalls);
        $this->assertSame('mcp__charts__render-chart-tool', $context->pendingToolCalls[0]['name']);
        $this->assertSame('{"x":1}', $context->pendingToolCalls[0]['arguments']);
    }

    public function test_synthesises_tool_call_from_single_object_in_content(): void
    {
        $payload = json_encode([
            'name' => 'mcp__charts__render-chart-tool',
            'parameters' => ['title' => 'Numbers 1-10'],
            'summary' => 'A chart',
        ]);

        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events), 'JSON content should be suppressed');
        $this->assertSame('', $context->streamedMessage, 'No streamed text should be saved');
        $this->assertCount(1, $context->pendingToolCalls);
        $this->assertSame('mcp__charts__render-chart-tool', $context->pendingToolCalls[0]['name']);
        $this->assertSame(['title' => 'Numbers 1-10'], json_decode($context->pendingToolCalls[0]['arguments'], true));
    }

    public function test_synthesises_tool_call_from_markdown_wrapped_json(): void
    {
        $payload = "```json\n".json_encode([
            'name' => 'mcp__currency__exchange-rate-tool',
            'parameters' => ['from' => 'USD', 'to' => 'DKK', 'amount' => 1000],
        ])."\n```";

        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(1, $context->pendingToolCalls);
        $this->assertSame('mcp__currency__exchange-rate-tool', $context->pendingToolCalls[0]['name']);
    }

    public function test_synthesises_multiple_tool_calls_from_array_in_content(): void
    {
        $payload = json_encode([
            ['name' => 'mcp__currency__exchange-rate-tool', 'parameters' => ['from' => 'USD', 'to' => 'DKK', 'amount' => 1000]],
            ['name' => 'mcp__charts__render-chart-tool', 'parameters' => ['title' => 'USD to DKK']],
        ]);

        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(2, $context->pendingToolCalls);
        $this->assertSame('mcp__currency__exchange-rate-tool', $context->pendingToolCalls[0]['name']);
        $this->assertSame('mcp__charts__render-chart-tool', $context->pendingToolCalls[1]['name']);
    }

    public function test_synthesises_tool_calls_from_tool_calls_wrapper(): void
    {
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

        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(1, $context->pendingToolCalls);
        $this->assertSame('mcp__charts__render-chart-tool', $context->pendingToolCalls[0]['name']);
        $this->assertSame(['title' => 'X'], json_decode($context->pendingToolCalls[0]['arguments'], true));
    }

    public function test_flushes_unparseable_json_content_as_text_delta_so_user_sees_something(): void
    {
        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => '{not really json']]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame(['{not really json'], $this->deltaTexts($events));
        $this->assertSame('{not really json', $context->streamedMessage);
        $this->assertSame([], $context->pendingToolCalls);
    }

    public function test_does_not_synthesise_when_real_tool_calls_already_present(): void
    {
        $payload = json_encode(['name' => 'should_be_ignored', 'parameters' => []]);

        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
            $this->sseChunk(['choices' => [['delta' => ['tool_calls' => [[
                'index' => 0,
                'id' => 'call_real',
                'function' => ['name' => 'real_tool', 'arguments' => '{}'],
            ]]]]]]),
            "data: [DONE]\n",
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(1, $context->pendingToolCalls);
        $this->assertSame('real_tool', $context->pendingToolCalls[0]['name']);
    }

    public function test_finalises_on_natural_eof_without_done_marker(): void
    {
        $payload = json_encode(['name' => 'mcp__charts__render-chart-tool', 'parameters' => []]);

        // No [DONE] line — upstream closed early.
        $events = $this->transform([
            $this->sseChunk(['choices' => [['delta' => ['content' => $payload]]]]),
        ], $context);

        $this->assertSame([], $this->deltaTexts($events));
        $this->assertCount(1, $context->pendingToolCalls);
    }

    /**
     * @param  array<int, string>  $bodyChunks
     * @return array<int, StreamEvent>
     */
    private function transform(array $bodyChunks, ?ChatContext &$context = null): array
    {
        $body = Utils::streamFor(implode('', $bodyChunks));
        $response = new Response(200, [], $body);
        $context = $this->makeContext();

        $transformer = new OpenAiCompatibleTransformer;

        return iterator_to_array($transformer->transform($response, $context), false);
    }

    private function sseChunk(array $payload): string
    {
        return 'data: '.json_encode($payload)."\n";
    }

    /**
     * @param  array<int, StreamEvent>  $events
     * @return array<int, string>
     */
    private function deltaTexts(array $events): array
    {
        $out = [];
        foreach ($events as $event) {
            if ($event->type->value === 'delta') {
                $out[] = $event->data['text'];
            }
        }

        return $out;
    }

    private function makeContext(): ChatContext
    {
        $reflection = new \ReflectionClass(ChatContext::class);
        /** @var ChatContext $context */
        $context = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('sources')->setValue($context, collect());
        $idProp = $reflection->getProperty('assistantMessageId');
        $idProp->setValue($context, 'asst-test');

        return $context;
    }
}
