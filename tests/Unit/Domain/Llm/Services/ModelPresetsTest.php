<?php

declare(strict_types=1);

use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Services\ModelPresets;
use Illuminate\Support\Str;

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
        mpModel('anthropic/claude-haiku-4.5'),
        mpModel('openai/gpt-5.4-mini'),
        mpModel('google/gemma-4-26b-a4b-it'),
        mpModel('deepseek/deepseek-v4.1-flash'),
        mpModel('mistralai/mistral-large-2512'),
    ];

    expect(mpIds(app(ModelPresets::class)->chatModels(AiProvider::OpenRouter, $listed)))
        ->toBe(['deepseek/deepseek-v4.1-flash', 'openai/gpt-5.4-mini', 'google/gemma-4-26b-a4b-it', 'anthropic/claude-haiku-4.5']);
});

it('passes over a chat model the provider says cannot call tools, but not one it says nothing about', function (): void {
    $listed = [mpModel('deepseek-v4.1-flash', tools: false), mpModel('llama-3.3-70b-instruct')];

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

    expect($presets->quickTaskModel(AiProvider::OpenAi, [mpModel('gpt-4.1-mini'), mpModel('gpt-6-luna')])?->id)->toBe('gpt-6-luna')
        ->and($presets->quickTaskModel(AiProvider::OpenAi, [mpModel('gpt-4o')]))->toBeNull()
        ->and($presets->quickTaskModel(null, [mpModel('llama3.1:8b')]))->toBeNull();
});

it('picks the first listed model that reads images, and none when no preset is listed', function (): void {
    $presets = app(ModelPresets::class);
    $model = fn (string $id, ?bool $vision = null): RemoteModelData => new RemoteModelData($id, $id, features: $vision === null ? [] : ['vision' => $vision]);

    expect($presets->visionModel(AiProvider::OpenRouter, [$model('qwen/qwen3-vl-30b-a3b-instruct', vision: false), $model('openai/gpt-6-luna')])?->id)->toBe('openai/gpt-6-luna')
        ->and($presets->visionModel(AiProvider::OpenRouter, [$model('openai/gpt-oss-120b', vision: false)]))->toBeNull()
        ->and($presets->visionModel(null, [$model('llava', vision: true)]))->toBeNull();
});

it('picks the first listed model that reads PDF files and images and calls tools, and none when no preset is listed', function (): void {
    $presets = app(ModelPresets::class);
    $model = fn (string $id, array $features = []): RemoteModelData => new RemoteModelData($id, $id, features: $features);

    expect($presets->fileModel(AiProvider::OpenRouter, [$model('openai/gpt-6-sol', ['files' => false]), $model('openai/gpt-4.1-mini', ['function_calling' => false]), $model('google/gemini-2.5-flash', ['vision' => true, 'files' => true])])?->id)->toBe('google/gemini-2.5-flash')
        ->and($presets->fileModel(AiProvider::OpenRouter, [$model('anthropic/claude-haiku-5.5')])?->id)->toBe('anthropic/claude-haiku-5.5')
        ->and($presets->fileModel(AiProvider::OpenRouter, [$model('openai/gpt-6-sol', ['vision' => false])]))->toBeNull()
        ->and($presets->fileModel(AiProvider::Groq, [$model('llama-3.3-70b-versatile')]))->toBeNull()
        ->and($presets->fileModel(null, [$model('gpt-4.1')]))->toBeNull();
});

it('recommends a model for chat file uploads for every provider with one that reads PDF files', function (AiProvider $provider): void {
    expect($provider->presetFileModels() !== [])->toBe($provider->can(AiCapability::Chat) && ! in_array($provider, [AiProvider::Scaleway, AiProvider::Groq, AiProvider::AzureOpenAi], true));
})->with(AiProvider::cases());

it('recommends chat, quick-task and image models for every provider with chat models, and none for the others', function (AiProvider $provider): void {
    // Azure OpenAI's models are deployments the admin names, so there is nothing to recommend.
    $chats = $provider->can(AiCapability::Chat) && $provider !== AiProvider::AzureOpenAi;

    expect($provider->presetChatModels() !== [])->toBe($chats)
        ->and($provider->presetQuickTaskModels() !== [])->toBe($chats)
        ->and($provider->presetVisionModels() !== [])->toBe($chats);
})->with(AiProvider::cases());

it('recommends five chat model makers on OpenRouter, and no Mistral model outside Mistral\'s own account', function (AiProvider $provider): void {
    $ids = [...array_merge(...$provider->presetChatModels()), ...$provider->presetQuickTaskModels(), ...$provider->presetVisionModels(), ...$provider->presetFileModels()];

    expect(array_filter($ids, fn (string $id): bool => Str::contains($id, 'mistral')))->toBe([]);
})->with(array_filter(AiProvider::cases(), fn (AiProvider $provider): bool => $provider !== AiProvider::Mistral));

it('gives OpenRouter five chat slots', function (): void {
    expect(AiProvider::OpenRouter->presetChatModels())->toHaveCount(5);
});
