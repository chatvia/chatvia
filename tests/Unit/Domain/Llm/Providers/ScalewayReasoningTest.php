<?php

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\LlmChat;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Scaleway's Qwen3 models think by default, which slows the app's own tasks;
 * its instruct models answer 400 to a `reasoning_effort` field they do not
 * know. Both follow from each model's facts, not its name: a model that
 * lists `none` gets it, a model that lists no effort gets no field.
 */
beforeEach(function (): void {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi']]]])]);
});

it('sends each Scaleway model the least effort it lists, or none', function (string $model, ?array $efforts, ?string $sent): void {
    app(LlmChat::class)->send(new ChatRequestData(
        testLlm($model, LlmProviderKind::Scaleway, ['capabilities' => $efforts !== null ? modelsDevEntry($efforts) : null]),
        [ChatMessageData::user('Hi')],
        AiCallTag::Guardrails,
    ));

    Http::assertSent(fn (Request $request): bool => ($request->data()['reasoning_effort'] ?? null) === $sent);
})->with([
    'qwen thinks less' => ['qwen3.6-35b-a3b', ['none', 'low', 'medium', 'high'], 'none'],
    'gpt-oss cannot stop thinking' => ['gpt-oss-120b', ['low', 'medium', 'high'], 'low'],
    'an instruct model gets no field' => ['mistral-small-3.2-24b-instruct-2506', null, null],
]);
