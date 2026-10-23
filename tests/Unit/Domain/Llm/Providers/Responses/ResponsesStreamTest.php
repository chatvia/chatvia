<?php

declare(strict_types=1);

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Conversation\Services\Streaming\Transformers\OpenAiCompatibleTransformer;
use Domain\Llm\Providers\Responses\ResponsesStream;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * OpenAI's Responses API stream, read as a Chat Completions stream: the
 * bodies below follow streams recorded from OpenAI (ids shortened, the
 * encrypted thinking left out). Read through the chat engine's transformer,
 * the summary of the thinking becomes `reasoning` events and never part of
 * the answer, function calls become the tool loop's tool calls, and the
 * token counts land under their Chat Completions names.
 */

/** @param  array<string, mixed>  $event */
function respEvent(array $event): string
{
    return 'event: '.$event['type']."\n".'data: '.json_encode($event)."\n\n";
}

function respCreated(): string
{
    return respEvent(['type' => 'response.created', 'response' => ['id' => 'resp_0a2', 'status' => 'in_progress', 'output' => []], 'sequence_number' => 0])
        .respEvent(['type' => 'response.in_progress', 'response' => ['id' => 'resp_0a2'], 'sequence_number' => 1]);
}

function respCompleted(int $input = 76, int $output = 18, int $reasoning = 0): string
{
    return respEvent(['type' => 'response.completed', 'response' => ['id' => 'resp_0a2', 'status' => 'completed', 'usage' => [
        'input_tokens' => $input,
        'input_tokens_details' => ['cache_write_tokens' => 0, 'cached_tokens' => 0],
        'output_tokens' => $output,
        'output_tokens_details' => ['reasoning_tokens' => $reasoning],
        'total_tokens' => $input + $output,
    ]]]);
}

/** A reasoning item with one summary part per given text, each sent in two deltas. */
function respReasoning(string $itemId, string ...$parts): string
{
    $body = respEvent(['type' => 'response.output_item.added', 'item' => ['id' => $itemId, 'type' => 'reasoning', 'content' => [], 'summary' => []], 'output_index' => 0]);

    foreach ($parts as $index => $text) {
        $half = intdiv(strlen($text), 2);
        $body .= respEvent(['type' => 'response.reasoning_summary_part.added', 'item_id' => $itemId, 'summary_index' => $index, 'part' => ['type' => 'summary_text', 'text' => '']])
            .respEvent(['type' => 'response.reasoning_summary_text.delta', 'item_id' => $itemId, 'summary_index' => $index, 'delta' => substr($text, 0, $half), 'obfuscation' => 'RVXZ'])
            .respEvent(['type' => 'response.reasoning_summary_text.delta', 'item_id' => $itemId, 'summary_index' => $index, 'delta' => substr($text, $half)])
            .respEvent(['type' => 'response.reasoning_summary_text.done', 'item_id' => $itemId, 'summary_index' => $index, 'text' => $text])
            .respEvent(['type' => 'response.reasoning_summary_part.done', 'item_id' => $itemId, 'summary_index' => $index]);
    }

    return $body.respEvent(['type' => 'response.output_item.done', 'item' => ['id' => $itemId, 'type' => 'reasoning', 'summary' => []]]);
}

function respText(string ...$deltas): string
{
    $body = respEvent(['type' => 'response.output_item.added', 'item' => ['id' => 'msg_0a2', 'type' => 'message', 'status' => 'in_progress', 'content' => [], 'phase' => 'final_answer', 'role' => 'assistant']])
        .respEvent(['type' => 'response.content_part.added', 'item_id' => 'msg_0a2', 'part' => ['type' => 'output_text', 'text' => '']]);

    foreach ($deltas as $delta) {
        $body .= respEvent(['type' => 'response.output_text.delta', 'item_id' => 'msg_0a2', 'content_index' => 0, 'delta' => $delta, 'logprobs' => []]);
    }

    return $body.respEvent(['type' => 'response.output_text.done', 'item_id' => 'msg_0a2', 'text' => implode('', $deltas)]);
}

/** A function call whose arguments arrive in the given pieces. */
function respFunctionCall(string $itemId, string $callId, string $name, string ...$arguments): string
{
    $body = respEvent(['type' => 'response.output_item.added', 'item' => ['id' => $itemId, 'type' => 'function_call', 'status' => 'in_progress', 'arguments' => '', 'call_id' => $callId, 'name' => $name]]);

    foreach ($arguments as $piece) {
        $body .= respEvent(['type' => 'response.function_call_arguments.delta', 'item_id' => $itemId, 'delta' => $piece]);
    }

    return $body.respEvent(['type' => 'response.function_call_arguments.done', 'item_id' => $itemId, 'arguments' => implode('', $arguments)]);
}

/** A context with only what the transformer reads. */
function respContext(bool $showsReasoning, array $offeredToolNames = []): ChatContext
{
    $reflection = new ReflectionClass(ChatContext::class);
    $context = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('sources')->setValue($context, collect());
    $reflection->getProperty('assistantMessageId')->setValue($context, 'asst-test');
    $context->offeredToolNames = $offeredToolNames;
    $context->showsReasoning = $showsReasoning;

    return $context;
}

/**
 * The Responses body read through the chat engine's transformer.
 *
 * @param  list<string>  $offeredToolNames
 * @return array{0: list<StreamEvent>, 1: ChatContext}
 */
function respTransform(string $body, bool $showsReasoning = true, array $offeredToolNames = []): array
{
    $context = respContext($showsReasoning, $offeredToolNames);
    $response = new Response(200, [], new ResponsesStream(Utils::streamFor($body)));

    return [iterator_to_array(new OpenAiCompatibleTransformer()->transform($response, $context), false), $context];
}

/**
 * @param  list<StreamEvent>  $events
 * @return list<string>
 */
function respTexts(array $events, EventType $type): array
{
    return array_values(array_map(
        static fn (StreamEvent $event): string => $event->data['text'],
        array_filter($events, static fn (StreamEvent $event): bool => $event->type === $type),
    ));
}

it('streams the answer text with the response id and usage', function (): void {
    [$events, $context] = respTransform(respCreated().respText('Hello ', 'world!').respCompleted());

    expect(respTexts($events, EventType::Delta))->toBe(['Hello ', 'world!'])
        ->and($context->streamedMessage)->toBe('Hello world!')
        ->and($context->generationId)->toBe('resp_0a2')
        ->and($context->usage)->toMatchArray([
            'prompt_tokens' => 76,
            'completion_tokens' => 18,
            'total_tokens' => 94,
        ]);
});

it('sends the summary of the thinking as reasoning, apart from the answer', function (): void {
    [$events, $context] = respTransform(
        respCreated()
        .respReasoning('rs_1', "**Analyzing integer bounds**\n\nI need to bound b.")
        .respReasoning('rs_2', '**Checking small cases**', '**Confirming**')
        .respText('The solutions are (2,0) and (0,-2).')
        .respCompleted(reasoning: 706),
    );

    expect(implode('', respTexts($events, EventType::Reasoning)))
        ->toBe("**Analyzing integer bounds**\n\nI need to bound b.\n\n**Checking small cases**\n\n**Confirming**")
        ->and($context->streamedMessage)->toBe('The solutions are (2,0) and (0,-2).')
        ->and($context->usage['completion_tokens_details'])->toBe(['reasoning_tokens' => 706]);
});

it('drops the thinking when the turn does not show it', function (): void {
    [$events, $context] = respTransform(respCreated().respReasoning('rs_1', 'Thinking.').respText('Answer.').respCompleted(), showsReasoning: false);

    expect(respTexts($events, EventType::Reasoning))->toBe([])
        ->and($context->streamedMessage)->toBe('Answer.');
});

it('collects function calls as the tool loop\'s tool calls', function (): void {
    [, $context] = respTransform(
        respCreated()
        .respReasoning('rs_1', 'The user wants the weather.')
        .respFunctionCall('fc_1', 'call_Ab1', 'get_weather', '{"ci', 'ty":"Paris"}')
        .respFunctionCall('fc_2', 'call_Cd2', 'get_time', '{}')
        .respCompleted(),
        offeredToolNames: ['get_weather', 'get_time'],
    );

    $calls = $context->takeToolCalls();

    expect($calls)->toHaveCount(2)
        ->and($calls[0]->id)->toBe('call_Ab1')
        ->and($calls[0]->name)->toBe('get_weather')
        ->and($calls[0]->arguments)->toBe('{"city":"Paris"}')
        ->and($calls[1]->id)->toBe('call_Cd2')
        ->and($calls[1]->name)->toBe('get_time')
        ->and($context->streamedMessage)->toBe('');
});

it('ends the read with an error when the response fails', function (): void {
    $stream = new ResponsesStream(Utils::streamFor(
        respCreated().respEvent(['type' => 'response.failed', 'response' => ['id' => 'resp_0a2', 'status' => 'failed', 'error' => ['code' => 'server_error', 'message' => 'The server had an error.']]]),
    ));

    expect(fn (): string => $stream->getContents())->toThrow(RuntimeException::class, 'The server had an error.');
});

it('passes each event on as soon as it arrives', function (): void {
    $lines = explode("\n", respCreated().respText('Hi', ' there'));
    $upstream = FnStream::decorate(Utils::streamFor(''), [
        'read' => function () use (&$lines): string {
            return array_shift($lines)."\n";
        },
        'eof' => function () use (&$lines): bool {
            return $lines === [];
        },
    ]);
    $stream = new ResponsesStream($upstream);

    expect($stream->read(1024))->toContain('"id":"resp_0a2"')
        ->and($stream->read(1024))->toContain('"content":"Hi"')
        ->and($stream->read(1024))->toContain('"content":" there"');
});
