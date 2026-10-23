<?php

declare(strict_types=1);

use Domain\Llm\Providers\Messages\MessagesPayload;

/**
 * A Chat Completions body written for Anthropic's Messages API: system
 * messages lifted to `system`, turns alternating with tool calls and results
 * as blocks, files as image and document blocks, the effort as adaptive
 * thinking, and a structured reply as `output_config.format`.
 */
function mpBody(array $payload): array
{
    return json_decode((string) json_encode(MessagesPayload::fromChatCompletions(['model' => 'claude-sonnet-5-5', ...$payload])), true);
}

it('lifts the system messages out and caches the prompt', function (): void {
    $body = mpBody(['messages' => [
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'Hi'],
        ['role' => 'system', 'content' => [['type' => 'text', 'text' => 'Use tools.']]],
    ]]);

    expect($body['system'])->toBe("You are helpful.\n\nUse tools.")
        ->and($body['messages'])->toBe([['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hi']]]])
        ->and($body['max_tokens'])->toBe(MessagesPayload::DEFAULT_MAX_TOKENS)
        ->and($body['cache_control'])->toBe(['type' => 'ephemeral']);
});

it('writes a tool round as tool_use and tool_result blocks in alternating turns', function (): void {
    $body = mpBody(['messages' => [
        ['role' => 'user', 'content' => 'Weather and time in Paris?'],
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [
            ['id' => 'toolu_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}']],
            ['id' => 'toolu_2', 'type' => 'function', 'function' => ['name' => 'get_time', 'arguments' => '']],
        ]],
        ['role' => 'tool', 'tool_call_id' => 'toolu_1', 'content' => 'Sunny'],
        ['role' => 'tool', 'tool_call_id' => 'toolu_2', 'content' => '14:00'],
    ]]);

    expect($body['messages'])->toBe([
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Weather and time in Paris?']]],
        ['role' => 'assistant', 'content' => [
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Paris']],
            ['type' => 'tool_use', 'id' => 'toolu_2', 'name' => 'get_time', 'input' => []],
        ]],
        ['role' => 'user', 'content' => [
            ['type' => 'tool_result', 'tool_use_id' => 'toolu_1', 'content' => 'Sunny'],
            ['type' => 'tool_result', 'tool_use_id' => 'toolu_2', 'content' => '14:00'],
        ]],
    ]);

    // An empty input goes as a JSON object, never a list.
    expect(json_encode(MessagesPayload::fromChatCompletions(['messages' => [
        ['role' => 'assistant', 'tool_calls' => [['id' => 't', 'function' => ['name' => 'x', 'arguments' => '']]]],
    ]])))->toContain('"input":{}');
});

it('joins turns of the same role, reads a staff reply as the assistant\'s, and skips empty text', function (): void {
    $body = mpBody(['messages' => [
        ['role' => 'user', 'content' => 'One'],
        ['role' => 'user', 'content' => 'Two'],
        ['role' => 'agent', 'content' => 'A teammate here.'],
        ['role' => 'assistant', 'content' => ''],
        ['role' => 'user', 'content' => 'Three'],
    ]]);

    expect($body['messages'])->toBe([
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'One'], ['type' => 'text', 'text' => 'Two']]],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'A teammate here.']]],
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Three']]],
    ]);
});

it('sends images and PDFs as blocks, from a data URL or a link', function (): void {
    $body = mpBody(['messages' => [['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Describe.'],
        ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,iVBORw0']],
        ['type' => 'image_url', 'image_url' => ['url' => 'https://example.test/cat.jpg']],
        ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,JVBERi0'],
    ]]]]);

    expect($body['messages'][0]['content'])->toBe([
        ['type' => 'text', 'text' => 'Describe.'],
        ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => 'iVBORw0']],
        ['type' => 'image', 'source' => ['type' => 'url', 'url' => 'https://example.test/cat.jpg']],
        ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => 'JVBERi0']],
    ]);
});

it('writes the tools and the tool choice in Messages form', function (mixed $choice, ?bool $parallel, ?array $expected): void {
    $body = mpBody([
        'messages' => [['role' => 'user', 'content' => 'Hi']],
        'tools' => [['type' => 'function', 'function' => ['name' => 'lookup', 'description' => 'Looks up.', 'parameters' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]]]],
        'tool_choice' => $choice,
        'parallel_tool_calls' => $parallel,
    ]);

    expect($body['tools'])->toBe([['name' => 'lookup', 'description' => 'Looks up.', 'input_schema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]]])
        ->and($body['tool_choice'] ?? null)->toBe($expected);
})->with([
    'auto' => ['auto', null, null],
    'none' => ['none', null, ['type' => 'none']],
    'required' => ['required', null, ['type' => 'any']],
    'one tool' => [['type' => 'function', 'function' => ['name' => 'lookup']], null, ['type' => 'tool', 'name' => 'lookup']],
    'one at a time' => ['auto', false, ['type' => 'auto', 'disable_parallel_tool_use' => true]],
]);

it('turns the effort into adaptive thinking, shown only when asked, without sampling', function (bool $shows, string $display): void {
    $body = MessagesPayload::fromChatCompletions(['messages' => [], 'reasoning_effort' => 'high', 'temperature' => 0.2, 'max_tokens' => 400], $shows);

    expect($body['thinking'])->toBe(['type' => 'adaptive', 'display' => $display])
        ->and($body['output_config'])->toBe(['effort' => 'high'])
        ->and($body['max_tokens'])->toBe(400)
        ->and($body)->not->toHaveKeys(['temperature', 'reasoning_effort']);
})->with([
    'shown' => [true, 'summarized'],
    'not shown' => [false, 'omitted'],
]);

it('keeps the temperature, and thinks not, without an effort', function (): void {
    $body = MessagesPayload::fromChatCompletions(['messages' => [], 'temperature' => 0.2, 'stop' => 'END']);

    expect($body['temperature'])->toBe(0.2)
        ->and($body['stop_sequences'])->toBe(['END'])
        ->and($body)->not->toHaveKeys(['thinking', 'output_config']);
});

it('asks for a structured reply by its JSON schema', function (): void {
    $schema = ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false];

    $body = MessagesPayload::fromChatCompletions(['messages' => [], 'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'verdict', 'strict' => true, 'schema' => $schema]]]);

    expect($body['output_config'])->toBe(['format' => ['type' => 'json_schema', 'schema' => $schema]]);
});

it('thinks on a budget for the models before adaptive thinking', function (string $effort, int $budget): void {
    $body = MessagesPayload::withThinkingBudget(MessagesPayload::fromChatCompletions(['messages' => [], 'reasoning_effort' => $effort, 'max_tokens' => 1000]));

    expect($body['thinking'])->toBe(['type' => 'enabled', 'budget_tokens' => $budget])
        ->and($body['max_tokens'])->toBe(1000 + $budget)
        ->and($body)->not->toHaveKey('output_config');
})->with([
    ['low', 2048],
    ['medium', 6144],
    ['high', 12288],
    ['max', 12288],
]);
