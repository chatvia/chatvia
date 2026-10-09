<?php

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\StructuredCompletion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The typed chat layer: a ChatRequestData becomes the provider's wire format,
 * and the provider's JSON becomes a ChatResponseData, so no caller parses
 * `choices.0.message` itself.
 */
beforeEach(function (): void {
    config(['services.scalewayai.api_key' => 'test-key', 'chatvia.ai.provider' => 'scaleway']);
});

it('sends only the options that are set', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    app(LlmChat::class)->send(new ChatRequestData(
        model: 'mistral-small',
        messages: [ChatMessageData::system('Be brief.'), ChatMessageData::user('Hi')],
        maxTokens: 10,
        responseSchema: new ResponseSchemaData('answer', ['type' => 'object']),
    ));

    Http::assertSent(fn (Request $request): bool => $request['messages'] === [
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => 'Hi'],
    ]
        && $request['max_tokens'] === 10
        && ! isset($request['temperature'])
        && $request['response_format']['json_schema']['name'] === 'answer'
        && $request['response_format']['json_schema']['strict'] === true);
});

it('reads the content, tool calls, usage and finish reason of a reply', function (): void {
    Http::fake(['*' => Http::response([
        'choices' => [[
            'finish_reason' => 'tool_calls',
            'message' => [
                'content' => [['type' => 'text', 'text' => 'Part one. '], ['type' => 'text', 'text' => 'Part two.']],
                'tool_calls' => [
                    ['id' => 'call_1', 'function' => ['name' => 'lookup', 'arguments' => '{"q":"x"}']],
                    ['id' => 'call_2', 'function' => ['name' => '', 'arguments' => '{}']],
                ],
            ],
        ]],
        'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
    ])]);

    $reply = app(LlmChat::class)->send(new ChatRequestData('m', [ChatMessageData::user('Hi')]));

    expect($reply->content)->toBe('Part one. Part two.')
        ->and($reply->toolCalls)->toHaveCount(1)
        ->and($reply->toolCalls[0]->name)->toBe('lookup')
        ->and($reply->toolCalls[0]->arguments)->toBe('{"q":"x"}')
        ->and($reply->usage->promptTokens)->toBe(12)
        ->and($reply->usage->totalTokens)->toBe(15)
        ->and($reply->finishReason)->toBe('tool_calls');
});

it('decodes a structured reply and fails loudly only when asked to', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => "```json\n{\"ok\":true}\n```"]]]])
        ->push(['choices' => [['message' => ['content' => 'no json here']]]])]);

    $completion = app(StructuredCompletion::class);
    $request = new ChatRequestData('m', [ChatMessageData::user('Hi')]);

    expect($completion->send($request)->data)->toBe(['ok' => true]);

    $reply = $completion->send($request);

    expect($reply->data)->toBeNull()
        ->and(fn () => $reply->requireData())->toThrow(InvalidStructuredReplyException::class);
});
