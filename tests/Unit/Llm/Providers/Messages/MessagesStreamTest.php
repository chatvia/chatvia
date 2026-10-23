<?php

declare(strict_types=1);

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Domain\Llm\Providers\Messages\MessagesStream;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * Anthropic's Messages stream, read as a Chat Completions stream: the bodies
 * below follow the event shapes in Anthropic's streaming guide. Read through
 * the chat engine's transformer, the thinking becomes `reasoning` events and
 * never part of the answer, tool_use blocks become the tool loop's tool
 * calls (indexed among the calls, not the blocks), and the token counts land
 * under their Chat Completions names.
 */

/** @param  array<string, mixed>  $event */
function msgEvent(array $event): string
{
    return 'event: '.$event['type']."\n".'data: '.json_encode($event)."\n\n";
}

function msgStart(int $input = 25, int $cacheRead = 0): string
{
    return msgEvent(['type' => 'message_start', 'message' => [
        'id' => 'msg_01Ab', 'type' => 'message', 'role' => 'assistant', 'content' => [], 'model' => 'claude-sonnet-5-5',
        'stop_reason' => null, 'usage' => ['input_tokens' => $input, 'cache_read_input_tokens' => $cacheRead, 'cache_creation_input_tokens' => 0, 'output_tokens' => 1],
    ]]).msgEvent(['type' => 'ping']);
}

function msgStop(int $output = 15, string $reason = 'end_turn'): string
{
    return msgEvent(['type' => 'message_delta', 'delta' => ['stop_reason' => $reason, 'stop_sequence' => null], 'usage' => ['output_tokens' => $output]])
        .msgEvent(['type' => 'message_stop']);
}

function msgText(int $index, string ...$deltas): string
{
    $body = msgEvent(['type' => 'content_block_start', 'index' => $index, 'content_block' => ['type' => 'text', 'text' => '']]);

    foreach ($deltas as $delta) {
        $body .= msgEvent(['type' => 'content_block_delta', 'index' => $index, 'delta' => ['type' => 'text_delta', 'text' => $delta]]);
    }

    return $body.msgEvent(['type' => 'content_block_stop', 'index' => $index]);
}

function msgThinking(int $index, string $text): string
{
    return msgEvent(['type' => 'content_block_start', 'index' => $index, 'content_block' => ['type' => 'thinking', 'thinking' => '']])
        .msgEvent(['type' => 'content_block_delta', 'index' => $index, 'delta' => ['type' => 'thinking_delta', 'thinking' => $text]])
        .msgEvent(['type' => 'content_block_delta', 'index' => $index, 'delta' => ['type' => 'signature_delta', 'signature' => 'EqQBCgIYAhIM']])
        .msgEvent(['type' => 'content_block_stop', 'index' => $index]);
}

/** A tool_use block whose input arrives in the given pieces. */
function msgToolUse(int $index, string $id, string $name, string ...$pieces): string
{
    $body = msgEvent(['type' => 'content_block_start', 'index' => $index, 'content_block' => ['type' => 'tool_use', 'id' => $id, 'name' => $name, 'input' => new stdClass]]);

    foreach (['', ...$pieces] as $piece) {
        $body .= msgEvent(['type' => 'content_block_delta', 'index' => $index, 'delta' => ['type' => 'input_json_delta', 'partial_json' => $piece]]);
    }

    return $body.msgEvent(['type' => 'content_block_stop', 'index' => $index]);
}

/**
 * The Messages body read through the chat engine's transformer.
 *
 * @param  list<string>  $offeredToolNames
 * @return array{0: list<StreamEvent>, 1: ChatContext}
 */
function msgTransform(string $body, bool $showsReasoning = true, array $offeredToolNames = []): array
{
    $reflection = new ReflectionClass(ChatContext::class);
    $context = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('sources')->setValue($context, collect());
    $reflection->getProperty('assistantMessageId')->setValue($context, 'asst-test');
    $context->offeredToolNames = $offeredToolNames;
    $context->showsReasoning = $showsReasoning;

    $response = new Response(200, [], new MessagesStream(Utils::streamFor($body)));

    return [iterator_to_array(new OpenAiCompatibleTransformer()->transform($response, $context), false), $context];
}

/**
 * @param  list<StreamEvent>  $events
 * @return list<string>
 */
function msgTexts(array $events, EventType $type): array
{
    return array_values(array_map(
        static fn (StreamEvent $event): string => $event->data['text'],
        array_filter($events, static fn (StreamEvent $event): bool => $event->type === $type),
    ));
}

it('streams the answer text with the message id and usage', function (): void {
    [$events, $context] = msgTransform(msgStart(input: 25, cacheRead: 100).msgText(0, 'Hello', '!').msgStop(output: 15));

    expect(msgTexts($events, EventType::Delta))->toBe(['Hello', '!'])
        ->and($context->streamedMessage)->toBe('Hello!')
        ->and($context->generationId)->toBe('msg_01Ab')
        ->and($context->usage)->toMatchArray([
            'prompt_tokens' => 125,
            'completion_tokens' => 15,
            'total_tokens' => 140,
            'prompt_tokens_details' => ['cached_tokens' => 100],
        ]);
});

it('sends the thinking as reasoning, apart from the answer', function (): void {
    [$events, $context] = msgTransform(msgStart().msgThinking(0, 'First idea.').msgThinking(1, 'Second idea.').msgText(2, 'Answer.').msgStop());

    expect(implode('', msgTexts($events, EventType::Reasoning)))->toBe("First idea.\n\nSecond idea.")
        ->and($context->streamedMessage)->toBe('Answer.');
});

it('drops the thinking when the turn does not show it', function (): void {
    [$events, $context] = msgTransform(msgStart().msgThinking(0, 'Thinking.').msgText(1, 'Answer.').msgStop(), showsReasoning: false);

    expect(msgTexts($events, EventType::Reasoning))->toBe([])
        ->and($context->streamedMessage)->toBe('Answer.');
});

it('collects tool_use blocks as the tool loop\'s tool calls', function (): void {
    [, $context] = msgTransform(
        msgStart()
        .msgText(0, 'Let me check.')
        .msgToolUse(1, 'toolu_01A', 'get_weather', '{"ci', 'ty": "Paris"}')
        .msgToolUse(2, 'toolu_01B', 'get_time')
        .msgStop(reason: 'tool_use'),
        offeredToolNames: ['get_weather', 'get_time'],
    );

    $calls = $context->takeToolCalls();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]->id)->toBe('toolu_01A')
        ->and($calls[0]->name)->toBe('get_weather')
        ->and($calls[0]->arguments)->toBe('{"city": "Paris"}')
        ->and($calls[1]->id)->toBe('toolu_01B')
        ->and($calls[1]->name)->toBe('get_time')
        ->and($calls[1]->arguments)->toBe('');
});

it('ends the read with an error on an error event', function (): void {
    $stream = new MessagesStream(Utils::streamFor(
        msgStart().msgEvent(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]),
    ));

    expect(fn (): string => $stream->getContents())->toThrow(RuntimeException::class, 'Overloaded');
});

it('passes each event on as soon as it arrives', function (): void {
    $lines = explode("\n", msgStart().msgText(0, 'Hi', ' there'));
    $upstream = FnStream::decorate(Utils::streamFor(''), [
        'read' => function () use (&$lines): string {
            return array_shift($lines)."\n";
        },
        'eof' => function () use (&$lines): bool {
            return $lines === [];
        },
    ]);
    $stream = new MessagesStream($upstream);

    expect($stream->read(1024))->toContain('"id":"msg_01Ab"')
        ->and($stream->read(1024))->toContain('"content":"Hi"')
        ->and($stream->read(1024))->toContain('"content":" there"');
});
