<?php

use App\Admin\Settings\Actions\ApplyRecommendedTaskModelsAction;
use App\Admin\Settings\AiSettings;
use Domain\Llm\Data\Catalog\RemoteModelData;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\Llm;

/**
 * ApplyRecommendedTaskModelsAction: the small model joins the catalog and
 * does the quick tasks; a model picked for a single task before is cleared.
 */
it('adds the small model and gives the tasks the preset\'s models', function (): void {
    $chat = testLlm('openai/gpt-6.1-sol', LlmProviderKind::OpenRouter);
    saveSettings(AiSettings::class, ['titles_model' => $chat->external_name]);

    $small = app(ApplyRecommendedTaskModelsAction::class)->execute(
        $chat->provider,
        new RemoteModelData('openai/gpt-6-luna', 'GPT-6 Luna', features: ['vision' => true, 'function_calling' => true]),
        $chat,
    );

    expect($small->internal_name)->toBe('openai/gpt-6-luna')
        ->and($small->llm_provider_id)->toBe($chat->llm_provider_id)
        ->and(Llm::query()->whereKey($small->id)->value('is_enabled'))->toBeTrue()
        ->and(savedSetting(AiSettings::class, 'task_model'))->toBe($small->external_name)
        ->and(savedSetting(AiSettings::class, 'eval_judge_model'))->toBe($chat->external_name)
        ->and(savedSetting(AiSettings::class, 'titles_model'))->toBeNull();
});
