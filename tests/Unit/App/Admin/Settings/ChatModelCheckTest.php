<?php

use App\Admin\Settings\ChatModelCheck;
use App\Admin\Settings\Data\ModelCheckData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The test of a chat model in the setup: a model that answers passes, a
 * provider limiting requests is busy, and a failure says what the provider
 * said, with what the provider behind OpenRouter said. The file test sends a
 * one-page PDF file and passes with the summary.
 */
function openRouterDraft(): LlmProvider
{
    return new LlmProvider(['kind' => LlmProviderKind::OpenRouter, 'name' => 'OpenRouter', 'api_key' => 'sk-or-test']);
}

it('passes a model that answers', function (): void {
    Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'OK']]]])]);

    expect(app(ChatModelCheck::class)->check(openRouterDraft(), 'openai/gpt-4.1'))->toEqual(ModelCheckData::passed());
});

it('calls a provider limiting requests busy, with what the provider behind OpenRouter said', function (): void {
    Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['error' => [
        'message' => 'Provider returned error',
        'code' => 429,
        'metadata' => ['provider_name' => 'Mistral', 'raw' => '{"object":"error","message":"Rate limit exceeded"}'],
    ]], 429)]);

    expect(app(ChatModelCheck::class)->check(openRouterDraft(), 'mistralai/mistral-large-2512'))->toEqual(ModelCheckData::busy(
        'Busy right now (error 429): the provider is limiting requests. Test again in a minute. Provider returned error. Mistral said: Rate limit exceeded',
    ));
});

it('says why a model failed, with plain text from the provider behind OpenRouter', function (): void {
    Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['error' => [
        'message' => 'Provider returned error',
        'metadata' => ['provider_name' => 'Groq', 'raw' => 'model is decommissioned'],
    ]], 400)]);

    expect(app(ChatModelCheck::class)->check(openRouterDraft(), 'meta-llama/llama-old'))->toEqual(ModelCheckData::failed(
        'The model did not answer (error 400): Provider returned error. Groq said: model is decommissioned',
    ));
});

it('sends a one-page PDF file and passes with the summary', function (): void {
    Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'A bakery\'s opening hours and orders.']]]])]);

    expect(app(ChatModelCheck::class)->checkFile(openRouterDraft(), 'openai/gpt-4.1'))->toEqual(ModelCheckData::passed('A bakery\'s opening hours and orders.'));

    Http::assertSent(fn (Request $request): bool => data_get($request->data(), 'messages.0.content.0.text') === 'Summarize this PDF file in one short sentence.'
        && data_get($request->data(), 'messages.0.content.1.file.filename') === 'chat-file.pdf'
        && Str::startsWith((string) data_get($request->data(), 'messages.0.content.1.file.file_data'), 'data:application/pdf;base64,JVBERi0'));
});

it('fails the file test when the model answers with no text', function (): void {
    Http::fake(['openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => '']]]])]);

    expect(app(ChatModelCheck::class)->checkFile(openRouterDraft(), 'openai/gpt-4.1'))->toEqual(ModelCheckData::failed('The model answered with no text.'));
});
