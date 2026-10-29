<?php

declare(strict_types=1);

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Events\LlmCallRefused;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\LlmProviders;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

/**
 * The typed chat layer: a ChatRequestData goes to the provider of its model,
 * and the provider's JSON becomes a ChatResponseData, so no caller parses
 * `choices.0.message` itself. A model its provider answers 404 for is
 * reported; other failures are not.
 */
it('sends each request to the provider of its model with its key, and reads the content, tool calls, usage and finish reason of the reply', function (): void {
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
    $groq = testLlm('llama-3.3-70b', LlmProviderKind::Groq);
    $local = testLlm('qwen3:8b', LlmProviderKind::Custom);
    $local->provider->update(['base_url' => 'http://ollama.test:11434/v1', 'api_key' => null]);

    $reply = app(LlmChat::class)->send(new ChatRequestData($groq, [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle));
    app(LlmChat::class)->send(new ChatRequestData($local->fresh(), [ChatMessageData::user('Hi')], AiCallTag::ConversationTitle));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
        && $request['model'] === 'llama-3.3-70b'
        && $request->header('Authorization') === ['Bearer test-key']);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'http://ollama.test:11434/v1/chat/completions'
        && $request['model'] === 'qwen3:8b'
        && ! $request->hasHeader('Authorization'));
    expect($reply->content)->toBe('Part one. Part two.')
        ->and($reply->toolCalls)->toHaveCount(1)
        ->and($reply->toolCalls[0]->name)->toBe('lookup')
        ->and($reply->toolCalls[0]->arguments)->toBe('{"q":"x"}')
        ->and($reply->usage->promptTokens)->toBe(12)
        ->and($reply->usage->totalTokens)->toBe(15)
        ->and($reply->finishReason)->toBe('tool_calls');
});

it('reports a model its provider answers 404 for, sent or not waited on, and no other failure or a model sent to another provider', function (): void {
    Event::fake([LlmCallRefused::class]);
    Http::fake(['*' => Http::response(['error' => ['message' => 'No allowed providers are available for the selected model.']], 404)]);
    $model = testLlm('gone-model');

    expect(fn () => app(LlmChat::class)->send(new ChatRequestData($model, [ChatMessageData::user('Hi')], AiCallTag::Guardrails)))->toThrow(RequestException::class)
        ->and(fn () => app(LlmChat::class)->sendAsync(new ChatRequestData($model, [ChatMessageData::user('Hi')], AiCallTag::Guardrails))->wait())->toThrow(RequestException::class);

    Http::swap(new Factory(app('events')));
    Http::fake(['*' => Http::response('upstream error', 500)]);

    expect(fn () => app(LlmChat::class)->send(new ChatRequestData(testLlm(), [ChatMessageData::user('Hi')], AiCallTag::Guardrails)))->toThrow(RequestException::class);

    Http::swap(new Factory(app('events')));
    Http::fake(['*' => Http::response('not here', 404)]);
    $other = testLlm('other-model');

    expect(fn () => app(LlmChat::class)->using(app(LlmProviders::class)->for($other))->send(new ChatRequestData(testLlm('named-model'), [ChatMessageData::user('Hi')], AiCallTag::Guardrails)))->toThrow(RequestException::class);

    Event::assertDispatchedTimes(LlmCallRefused::class, 2);
    Event::assertDispatched(LlmCallRefused::class, fn (LlmCallRefused $event): bool => $event->llmId === $model->id);
});
