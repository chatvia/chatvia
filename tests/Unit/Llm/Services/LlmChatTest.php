<?php

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Data\Chat\ChatResponseData;
use Domain\Llm\Data\Chat\ResponseSchemaData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Exceptions\InvalidStructuredReplyException;
use Domain\Llm\Exceptions\LlmProviderUnavailableException;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\StructuredCompletion;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spectra\Spectra;

/**
 * The typed chat layer: a ChatRequestData becomes the provider's wire format
 * and goes to the provider of its model, and the provider's JSON becomes a
 * ChatResponseData, so no caller parses `choices.0.message` itself.
 */
it('sends each request to the provider of its model, with its key', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    $groq = testLlm('llama-3.3-70b', LlmProviderKind::Groq);
    $local = testLlm('qwen3:8b', LlmProviderKind::Custom);
    $local->provider->update(['base_url' => 'http://ollama.test:11434/v1', 'api_key' => null]);

    app(LlmChat::class)->send(new ChatRequestData($groq, [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle));
    app(LlmChat::class)->send(new ChatRequestData($local->fresh(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request['model'] === 'llama-3.3-70b'
        && $request->header('Authorization') === ['Bearer test-key']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://ollama.test:11434/v1/chat/completions'
        && $request['model'] === 'qwen3:8b'
        && ! $request->hasHeader('Authorization'));
});

it('refuses a model whose provider is turned off', function (): void {
    Http::fake();
    $model = testLlm();
    $model->provider->update(['is_enabled' => false]);

    expect(fn (): ChatResponseData => app(LlmChat::class)->send(new ChatRequestData($model->fresh(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle)))
        ->toThrow(LlmProviderUnavailableException::class);

    Http::assertNothingSent();
});

it('sends the request with its tag in Spectra', function (): void {
    config(['spectra.enabled' => true]);
    $tags = null;
    Http::fake(['*' => function () use (&$tags) {
        $tags = app(Spectra::class)->startRequest('openai', 'gpt-test')->tags;

        return Http::response(['choices' => [['message' => ['content' => 'ok']]]]);
    }]);

    app(LlmChat::class)->send(new ChatRequestData(testLlm(), [ChatMessageData::user('Hi')], AiCallTag::FollowUpRewrite));

    expect($tags)->toBe(['follow-up-rewrite']);
});

it('sends only the options that are set', function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

    app(LlmChat::class)->send(new ChatRequestData(
        model: testLlm('mistral-small'),
        messages: [ChatMessageData::system('Be brief.'), ChatMessageData::user('Hi')],
        tag: AiCallTag::ConversationTitle,
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

    $reply = app(LlmChat::class)->send(new ChatRequestData(testLlm(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle));

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
    $request = new ChatRequestData(testLlm(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle);

    expect($completion->send($request)->data)->toBe(['ok' => true]);

    $reply = $completion->send($request);

    expect($reply->data)->toBeNull()
        ->and(fn (): array => $reply->requireData())->toThrow(InvalidStructuredReplyException::class);
});
