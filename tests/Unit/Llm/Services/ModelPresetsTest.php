<?php

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Services\ModelPresets;

/**
 * ModelPresets: the recommended models are picked from what the provider
 * lists now, so a model it no longer serves is passed over, and nothing is
 * guessed for quick tasks when none of the recommended ones is listed.
 */
function mpModel(string $id, ?bool $tools = null): RemoteModelData
{
    return new RemoteModelData($id, $id, features: $tools === null ? [] : ['function_calling' => $tools]);
}

/**
 * @param  list<RemoteModelData>  $models
 * @return list<string>
 */
function mpIds(array $models): array
{
    return array_map(fn (RemoteModelData $model): string => $model->id, $models);
}

it('picks the first listed id of each chat slot, default first', function (): void {
    $listed = [
        mpModel('openai/gpt-oss-120b'),
        mpModel('qwen/qwen3-235b-a22b-2507'),
        mpModel('openai/gpt-5.6-terra'),
        mpModel('google/gemini-2.5-flash'),
    ];

    expect(mpIds(app(ModelPresets::class)->chatModels(AiProvider::OpenRouter, $listed)))
        ->toBe(['openai/gpt-5.6-terra', 'qwen/qwen3-235b-a22b-2507', 'google/gemini-2.5-flash', 'openai/gpt-oss-120b']);
});

it('passes over a chat model the provider says cannot call tools, but not one it says nothing about', function (): void {
    $listed = [mpModel('gpt-oss-120b', tools: false), mpModel('llama-3.3-70b-instruct')];

    expect(mpIds(app(ModelPresets::class)->chatModels(AiProvider::Scaleway, $listed)))->toBe(['llama-3.3-70b-instruct']);
});

it('falls back to the first model that calls tools, else the first, when no preset is listed', function (): void {
    $presets = app(ModelPresets::class);

    expect(mpIds($presets->chatModels(AiProvider::OpenRouter, [mpModel('a/one'), mpModel('b/two', tools: true)])))->toBe(['b/two'])
        ->and(mpIds($presets->chatModels(null, [mpModel('llama3.1:8b')])))->toBe(['llama3.1:8b'])
        ->and($presets->chatModels(AiProvider::OpenRouter, []))->toBe([]);
});

it('picks the first listed quick-task model, and none when no preset is listed', function (): void {
    $presets = app(ModelPresets::class);

    expect($presets->quickTaskModel(AiProvider::OpenAi, [mpModel('gpt-4.1-nano'), mpModel('gpt-5.6-luna')])?->id)->toBe('gpt-5.6-luna')
        ->and($presets->quickTaskModel(AiProvider::OpenAi, [mpModel('gpt-4o')]))->toBeNull()
        ->and($presets->quickTaskModel(null, [mpModel('llama3.1:8b')]))->toBeNull();
});

it('picks the first listed model that reads images, and none when no preset is listed', function (): void {
    $presets = app(ModelPresets::class);
    $model = fn (string $id, ?bool $vision = null): RemoteModelData => new RemoteModelData($id, $id, features: $vision === null ? [] : ['vision' => $vision]);

    expect($presets->visionModel(AiProvider::OpenRouter, [$model('openai/gpt-6-luna', vision: false), $model('google/gemini-3.5-flash-lite')])?->id)->toBe('google/gemini-3.5-flash-lite')
        ->and($presets->visionModel(AiProvider::OpenRouter, [$model('openai/gpt-oss-120b', vision: false)]))->toBeNull()
        ->and($presets->visionModel(null, [$model('llava', vision: true)]))->toBeNull();
});

it('recommends chat, quick-task and image models for every provider with chat models', function (): void {
    foreach (AiProvider::cases() as $provider) {
        $chats = $provider->can(AiCapability::Chat);

        expect($provider->presetChatModels() !== [])->toBe($chats, $provider->value)
            ->and($provider->presetQuickTaskModels() !== [])->toBe($chats, $provider->value)
            ->and($provider->presetVisionModels() !== [])->toBe($chats, $provider->value);
    }
});
