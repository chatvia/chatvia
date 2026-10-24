<?php

declare(strict_types=1);

use Domain\Llm\Providers\Responses\ResponsesPayload;

/**
 * A Chat Completions body written for OpenAI's Responses API: the history
 * with its tool calls and results, the tools, the reasoning effort with a
 * summary, and nothing kept at OpenAI.
 */
function rpBody(array $extra = []): array
{
    return ResponsesPayload::fromChatCompletions([
        'model' => 'gpt-6.1-sol',
        'messages' => [
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Weather?'], ['type' => 'image_url', 'image_url' => ['url' => 'https://img.test/a.png']]]],
            ['role' => 'assistant', 'content' => '', 'tool_calls' => [
                ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}']],
            ]],
            ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => 'Sunny'],
            ['role' => 'assistant', 'content' => 'It is sunny.'],
            ['role' => 'user', 'content' => 'Thanks'],
        ],
        'tools' => [['type' => 'function', 'function' => ['name' => 'get_weather', 'description' => 'Weather for a city', 'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]]]]],
        'tool_choice' => 'auto',
        'reasoning_effort' => 'medium',
        'max_completion_tokens' => 500,
        'stream_options' => ['include_usage' => true],
        'presence_penalty' => 0.5,
        'top_k' => 40,
        ...$extra,
    ]);
}

it('writes the history as input items', function (): void {
    expect(rpBody()['input'])->toBe([
        ['role' => 'system', 'content' => 'Be brief.'],
        ['role' => 'user', 'content' => [
            ['type' => 'input_text', 'text' => 'Weather?'],
            ['type' => 'input_image', 'image_url' => 'https://img.test/a.png', 'detail' => 'auto'],
        ]],
        ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
        ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'Sunny'],
        ['role' => 'assistant', 'content' => 'It is sunny.'],
        ['role' => 'user', 'content' => 'Thanks'],
    ]);
});

it('moves the tools up a level, with strict schemas off', function (): void {
    expect(rpBody()['tools'])->toBe([[
        'type' => 'function',
        'name' => 'get_weather',
        'description' => 'Weather for a city',
        'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
        'strict' => false,
    ]]);
});

it('asks for the effort, streamed and not stored', function (): void {
    $body = rpBody();

    expect($body['reasoning'])->toBe(['effort' => 'medium'])
        ->and($body['presence_penalty'])->toBe(0.5)
        ->and($body['stream'])->toBeTrue()
        ->and($body['store'])->toBeFalse()
        ->and($body['max_output_tokens'])->toBe(500)
        ->and($body['tool_choice'])->toBe('auto')
        ->and($body)->not->toHaveKeys(['messages', 'reasoning_effort', 'stream_options', 'top_k', 'max_completion_tokens']);
});

it('adds a summary of the thinking on request, only when the model reasons', function (): void {
    expect(ResponsesPayload::withSummary(rpBody())['reasoning'])->toBe(['effort' => 'medium', 'summary' => 'auto'])
        ->and(ResponsesPayload::withSummary(rpBody(['reasoning_effort' => null])))->not->toHaveKey('reasoning');
});

it('sends a PDF as an input file with its bytes and name', function (): void {
    $body = ResponsesPayload::fromChatCompletions(['model' => 'gpt-6-sol', 'messages' => [['role' => 'user', 'content' => [
        ['type' => 'document_url', 'document_url' => 'data:application/pdf;base64,JVBERg==', 'document_name' => 'report.pdf'],
    ]]]]);

    expect($body['input'][0]['content'])->toBe([['type' => 'input_file', 'filename' => 'report.pdf', 'file_data' => 'data:application/pdf;base64,JVBERg==']]);
});
