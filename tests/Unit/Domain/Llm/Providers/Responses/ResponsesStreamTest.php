<?php

declare(strict_types=1);

use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Streaming\Sse\EventType;
use Domain\Conversation\Services\Streaming\Sse\StreamEvent;
use Domain\Llm\Providers\Responses\ResponsesStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Str;
use Tests\Support\Llm\ProviderStream;

/**
 * OpenAI's Responses API stream, read as a Chat Completions stream: the
 * bodies below follow streams recorded from OpenAI (ids shortened, the
 * encrypted thinking left out). Read through the chat engine's transformer,
 * the summary of the thinking becomes `reasoning` events and never part of
 * the answer, function calls become the tool loop's tool calls, and the
 * token counts land under their Chat Completions names.
 */
function respCreated(): string
{
    return ProviderStream::event(['type' => 'response.created', 'response' => ['id' => 'resp_0a2', 'status' => 'in_progress', 'output' => []], 'sequence_number' => 0])
        .ProviderStream::event(['type' => 'response.in_progress', 'response' => ['id' => 'resp_0a2'], 'sequence_number' => 1]);
}

function respCompleted(int $input = 76, int $output = 18, int $reasoning = 0): string
{
    return ProviderStream::event(['type' => 'response.completed', 'response' => ['id' => 'resp_0a2', 'status' => 'completed', 'usage' => [
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
    $body = ProviderStream::event(['type' => 'response.output_item.added', 'item' => ['id' => $itemId, 'type' => 'reasoning', 'content' => [], 'summary' => []], 'output_index' => 0]);

    foreach ($parts as $index => $text) {
        $half = intdiv(Str::length($text), 2);
        $body .= ProviderStream::event(['type' => 'response.reasoning_summary_part.added', 'item_id' => $itemId, 'summary_index' => $index, 'part' => ['type' => 'summary_text', 'text' => '']])
            .ProviderStream::event(['type' => 'response.reasoning_summary_text.delta', 'item_id' => $itemId, 'summary_index' => $index, 'delta' => Str::substr($text, 0, $half), 'obfuscation' => 'RVXZ'])
            .ProviderStream::event(['type' => 'response.reasoning_summary_text.delta', 'item_id' => $itemId, 'summary_index' => $index, 'delta' => Str::substr($text, $half)])
            .ProviderStream::event(['type' => 'response.reasoning_summary_text.done', 'item_id' => $itemId, 'summary_index' => $index, 'text' => $text])
            .ProviderStream::event(['type' => 'response.reasoning_summary_part.done', 'item_id' => $itemId, 'summary_index' => $index]);
    }

    return $body.ProviderStream::event(['type' => 'response.output_item.done', 'item' => ['id' => $itemId, 'type' => 'reasoning', 'summary' => []]]);
}

function respText(string ...$deltas): string
{
    $body = ProviderStream::event(['type' => 'response.output_item.added', 'item' => ['id' => 'msg_0a2', 'type' => 'message', 'status' => 'in_progress', 'content' => [], 'phase' => 'final_answer', 'role' => 'assistant']])
        .ProviderStream::event(['type' => 'response.content_part.added', 'item_id' => 'msg_0a2', 'part' => ['type' => 'output_text', 'text' => '']]);

    foreach ($deltas as $delta) {
        $body .= ProviderStream::event(['type' => 'response.output_text.delta', 'item_id' => 'msg_0a2', 'content_index' => 0, 'delta' => $delta, 'logprobs' => []]);
    }

    return $body.ProviderStream::event(['type' => 'response.output_text.done', 'item_id' => 'msg_0a2', 'text' => implode('', $deltas)]);
}

/** A function call whose arguments arrive in the given pieces. */
function respFunctionCall(string $itemId, string $callId, string $name, string ...$arguments): string
{
    $body = ProviderStream::event(['type' => 'response.output_item.added', 'item' => ['id' => $itemId, 'type' => 'function_call', 'status' => 'in_progress', 'arguments' => '', 'call_id' => $callId, 'name' => $name]]);

    foreach ($arguments as $piece) {
        $body .= ProviderStream::event(['type' => 'response.function_call_arguments.delta', 'item_id' => $itemId, 'delta' => $piece]);
    }

    return $body.ProviderStream::event(['type' => 'response.function_call_arguments.done', 'item_id' => $itemId, 'arguments' => implode('', $arguments)]);
}

/**
 * The Responses body read through the chat engine's transformer.
 *
 * @param  list<string>  $offeredToolNames
 * @return array{0: list<StreamEvent>, 1: ChatContext}
 */
function respTransform(string $body, bool $showsReasoning = true, array $offeredToolNames = []): array
{
    return ProviderStream::transform(new ResponsesStream(Utils::streamFor($body)), $showsReasoning, $offeredToolNames);
}

it('streams the answer with the response id and usage, and the summary of the thinking as reasoning apart from it', function (): void {
    [$events, $context] = respTransform(
        respCreated()
        .respReasoning('rs_1', "**Analyzing integer bounds**\n\nI need to bound b.")
        .respReasoning('rs_2', '**Checking small cases**', '**Confirming**')
        .respText('Hello ', 'world!')
        .respCompleted(reasoning: 706),
    );

    expect(ProviderStream::texts($events, EventType::Delta))->toBe(['Hello ', 'world!'])
        ->and(implode('', ProviderStream::texts($events, EventType::Reasoning)))
        ->toBe("**Analyzing integer bounds**\n\nI need to bound b.\n\n**Checking small cases**\n\n**Confirming**")
        ->and($context->streamedMessage)->toBe('Hello world!')
        ->and($context->generationId)->toBe('resp_0a2')
        ->and($context->usage)->toMatchArray([
            'prompt_tokens' => 76,
            'completion_tokens' => 18,
            'total_tokens' => 94,
        ])
        ->and($context->usage['completion_tokens_details'])->toBe(['reasoning_tokens' => 706]);
});

it('drops the thinking of a turn that does not show it, and collects its function calls as the tool loop\'s tool calls', function (): void {
    [$events, $context] = respTransform(
        respCreated()
        .respReasoning('rs_1', 'The user wants the weather.')
        .respFunctionCall('fc_1', 'call_Ab1', 'get_weather', '{"ci', 'ty":"Paris"}')
        .respFunctionCall('fc_2', 'call_Cd2', 'get_time', '{}')
        .respCompleted(),
        showsReasoning: false,
        offeredToolNames: ['get_weather', 'get_time'],
    );

    $calls = $context->takeToolCalls();

    expect(ProviderStream::texts($events, EventType::Reasoning))->toBe([])
        ->and($calls)->toHaveCount(2)
        ->and($calls[0]->id)->toBe('call_Ab1')
        ->and($calls[0]->name)->toBe('get_weather')
        ->and($calls[0]->arguments)->toBe('{"city":"Paris"}')
        ->and($calls[1]->id)->toBe('call_Cd2')
        ->and($calls[1]->name)->toBe('get_time')
        ->and($context->streamedMessage)->toBe('');
});

it('passes each event on as soon as it arrives, and ends the read with an error when the response fails', function (): void {
    $stream = new ResponsesStream(ProviderStream::dripFed(
        respCreated()
        .respText('Hi', ' there')
        .ProviderStream::event(['type' => 'response.failed', 'response' => ['id' => 'resp_0a2', 'status' => 'failed', 'error' => ['code' => 'server_error', 'message' => 'The server had an error.']]]),
    ));

    expect($stream->read(1024))->toContain('"id":"resp_0a2"')
        ->and($stream->read(1024))->toContain('"content":"Hi"')
        ->and($stream->read(1024))->toContain('"content":" there"')
        ->and(fn (): string => $stream->getContents())->toThrow(RuntimeException::class, 'The server had an error.');
});
