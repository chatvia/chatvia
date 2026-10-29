<?php

declare(strict_types=1);

use Domain\Llm\Providers\Messages\MessagesPayload;

/**
 * A Chat Completions body written for Anthropic's Messages API: system
 * messages lifted to `system`, turns alternating with tool calls and results
 * as blocks, files as image and document blocks, the tool choice in Messages
 * form, and the effort as adaptive thinking or, for older models, a budget.
 */
function mpBody(array $payload): array
{
    return json_decode((string) json_encode(MessagesPayload::fromChatCompletions(['model' => 'claude-sonnet-5-5', ...$payload])), true);
}

it('lifts the system messages out, writes tool rounds as blocks in alternating turns, joins turns of one role and sends files as blocks', function (): void {
    $body = mpBody(['messages' => [
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Weather and time in Paris?'],
        ['role' => 'user', 'content' => [
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,iVBORw0']],
            ['type' => 'image_url', 'image_url' => ['url' => 'https://example.test/cat.jpg']],
            ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,JVBERi0'],
        ]],
        ['role' => 'system', 'content' => [['type' => 'text', 'text' => 'Use tools.']]],
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'toolu_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}']],
            ['id' => 'toolu_2', 'type' => 'function', 'function' => ['name' => 'get_time', 'arguments' => '']],
        ]],
        ['role' => 'tool', 'tool_call_id' => 'toolu_1', 'content' => 'Sunny'],
        ['role' => 'tool', 'tool_call_id' => 'toolu_2', 'content' => '14:00'],
        ['role' => 'agent', 'content' => 'A teammate here.'],
        ['role' => 'assistant', 'content' => ''],
        ['role' => 'user', 'content' => 'Thanks'],
    ]]);

    expect($body['system'])->toBe("You are helpful.\n\nUse tools.")
        ->and($body['max_tokens'])->toBe(MessagesPayload::DEFAULT_MAX_TOKENS)
        ->and($body['cache_control'])->toBe(['type' => 'ephemeral'])
        ->and($body['messages'])->toBe([
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Weather and time in Paris?'],
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'iVBORw0']],
                ['type' => 'image', 'source' => ['type' => 'url', 'url' => 'https://example.test/cat.jpg']],
                ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => 'JVBERi0']],
            ]],
            ['role' => 'assistant', 'content' => [
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
                ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'get_time', 'input' => []],
            ]],
            ['role' => 'user', 'content' => [
                ['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => 'Sunny'],
                ['type' => 'tool_result', 'tool_use_id' => 'toolu_2', 'content' => '14:00'],
            ]],
            ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'A teammate here.']]],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Thanks']]],
        ])
        ->and(json_encode(MessagesPayload::fromChatCompletions(['messages' => [
            ['role' => 'assistant', 'tool_calls' => [['id' => 't', 'function' => ['name' => 'x', 'arguments' => '']]]],
        ]])))->toContain('"input":{}');
});

it('writes the tool choice in Messages form, the effort as adaptive thinking without sampling, and a budget for older models', function (): void {
    $tools = fn (mixed $choice, ?bool $parallel): array => mpBody([
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'tools' => [['type' => 'function', 'function' => ['name' => 'lookup', 'description' => 'Looks up.', 'parameters' => ['type' => 'object']]]],
        'tool_choice' => $choice,
        'parallel_tool_calls' => $parallel,
    ]);
    $shown = MessagesPayload::fromChatCompletions(['messages' => [], 'reasoning_effort' => 'high', 'temperature' => 0.2, 'max_tokens' => 400], true);
    $hidden = MessagesPayload::fromChatCompletions(['messages' => [], 'reasoning_effort' => 'high'], false);
    $plain = MessagesPayload::fromChatCompletions(['messages' => [], 'temperature' => 0.2, 'stop' => 'END']);
    $budget = MessagesPayload::withThinkingBudget(MessagesPayload::fromChatCompletions(['messages' => [], 'reasoning_effort' => 'low', 'max_tokens' => 1000]));

    expect($tools('auto', null)['tools'])->toBe([['name' => 'lookup', 'description' => 'Looks up.', 'input_schema' => ['type' => 'object']]])
        ->and($tools('auto', null))->not->toHaveKey('tool_choice')
        ->and($tools('none', null)['tool_choice'])->toBe(['type' => 'none'])
        ->and($tools('required', null)['tool_choice'])->toBe(['type' => 'any'])
        ->and($tools(['type' => 'function', 'function' => ['name' => 'lookup']], null)['tool_choice'])->toBe(['type' => 'tool', 'name' => 'lookup'])
        ->and($tools('auto', false)['tool_choice'])->toBe(['type' => 'auto', 'disable_parallel_tool_use' => true])
        ->and($shown['thinking'])->toBe(['type' => 'adaptive', 'display' => 'summarized'])
        ->and($shown['output_config'])->toBe(['effort' => 'high'])
        ->and($shown['max_tokens'])->toBe(400)
        ->and($shown)->not->toHaveKeys(['temperature', 'reasoning_effort'])
        ->and($hidden['thinking'])->toBe(['type' => 'adaptive', 'display' => 'omitted'])
        ->and($plain['temperature'])->toBe(0.2)
        ->and($plain['stop_sequences'])->toBe(['END'])
        ->and($plain)->not->toHaveKeys(['thinking', 'output_config'])
        ->and($budget['thinking'])->toBe(['type' => 'enabled', 'budget_tokens' => 2048])
        ->and($budget['max_tokens'])->toBe(3048)
        ->and($budget)->not->toHaveKey('output_config');
});
