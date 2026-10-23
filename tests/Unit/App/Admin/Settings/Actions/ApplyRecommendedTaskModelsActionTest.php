<?php

use App\Admin\Settings\Actions\ApplyRecommendedTaskModelsAction;
use App\Admin\Settings\AiSettings;
use App\Admin\Settings\KnowledgeSettings;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;

/**
 * ApplyRecommendedTaskModelsAction: the small model joins the catalog and
 * does the quick tasks; a model picked for a single task before is cleared.
 */
it('adds the small model and gives the tasks the preset\'s models', function (): void {
    $chat = testLlm('openai/gpt-6.1-sol', LlmProviderKind::OpenRouter);
    saveSettings(AiSettings::class, ['titles_model' => (string) $chat->id]);

    $small = app(ApplyRecommendedTaskModelsAction::class)->execute(
        $chat->provider,
        new RemoteModelData('openai/gpt-6-luna', 'GPT-6 Luna', features: ['vision' => true, 'function_calling' => true]),
        $chat,
    );

    expect($small->name)->toBe('openai/gpt-6-luna')
        ->and($small->llm_provider_id)->toBe($chat->llm_provider_id)
        ->and(Llm::query()->whereKey($small->id)->value('is_enabled'))->toBeTrue()
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBe((string) $small->id)
        ->and(savedSetting(AiSettings::class, 'eval_judge_model'))->toBe((string) $chat->id)
        ->and(savedSetting(AiSettings::class, 'titles_model'))->toBeNull()
        // No model that reads images: image descriptions go off.
        ->and(savedSetting(AiSettings::class, 'images_model'))->toBeNull()
        ->and(savedSetting(KnowledgeSettings::class, 'describe_images'))->toBeFalse();
});

it('describes images with the recommended model that reads them', function (): void {
    $chat = testLlm('openai/gpt-6.1-sol', LlmProviderKind::OpenRouter);

    app(ApplyRecommendedTaskModelsAction::class)->execute(
        $chat->provider,
        new RemoteModelData('openai/gpt-oss-20b', 'gpt-oss-20b'),
        $chat,
        new RemoteModelData('google/gemini-3.5-flash-lite', 'Gemini 3.5 Flash Lite', features: ['vision' => true]),
    );

    expect(savedSetting(AiSettings::class, 'images_model'))->toBe((string) Llm::query()->where('name', 'google/gemini-3.5-flash-lite')->value('id'))
        ->and(savedSetting(KnowledgeSettings::class, 'describe_images'))->toBeNull();
});
