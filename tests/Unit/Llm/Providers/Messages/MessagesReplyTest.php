<?php

declare(strict_types=1);

use Domain\Llm\Providers\Messages\MessagesReply;

/**
 * A whole Messages reply read as a Chat Completions reply: the text, the
 * tool calls with their input as JSON, why it ended, and the token counts
 * with the cached input counted in the prompt.
 */
it('reads the text, the tool calls, the finish reason and the usage', function (): void {
    $reply = MessagesReply::toChatCompletion([
        'id' => 'msg_01Ab',
        'model' => 'claude-haiku-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => 'Hmm.', 'signature' => 'sig'],
            ['type' => 'text', 'text' => 'Let me '],
            ['type' => 'text', 'text' => 'look.'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'lookup', 'input' => ['q' => 'Paris']],
        ],
        'stop_reason' => 'tool_use',
        'usage' => ['input_tokens' => 10, 'cache_read_input_tokens' => 90, 'cache_creation_input_tokens' => 5, 'output_tokens' => 20],
    ]);

    expect($reply['id'])->toBe('msg_01Ab')
        ->and($reply['choices'][0]['message'])->toBe([
            'role' => 'assistant',
            'content' => 'Let me look.',
            'tool_calls' => [['id' => 'toolu_1', 'type' => 'function', 'function' => ['name' => 'lookup', 'arguments' => '{"q":"Paris"}']]],
        ])
        ->and($reply['choices'][0]['finish_reason'])->toBe('tool_calls')
        ->and($reply['usage'])->toMatchArray([
            'prompt_tokens' => 105,
            'completion_tokens' => 20,
            'total_tokens' => 125,
            'prompt_tokens_details' => ['cached_tokens' => 90],
        ]);
});

it('names why a reply ended as Chat Completions does', function (string $stopReason, string $finishReason): void {
    expect(MessagesReply::toChatCompletion(['content' => [], 'stop_reason' => $stopReason])['choices'][0]['finish_reason'])->toBe($finishReason);
})->with([
    ['end_turn', 'stop'],
    ['stop_sequence', 'stop'],
    ['max_tokens', 'length'],
    ['refusal', 'content_filter'],
]);
