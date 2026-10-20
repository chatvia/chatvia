<?php

use App\Admin\Settings\TaskModelPreset;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Models\Llm;

/**
 * TaskModelPreset: the small model does every quick task, the tasks that
 * need a large model get the chat model, and image descriptions get the
 * model that reads images, or none.
 */
function tmpModel(string $alias, ?bool $vision = null): Llm
{
    return new Llm(['external_name' => $alias, 'features' => $vision === null ? [] : ['vision' => $vision]]);
}

it('gives quick tasks the small model and large tasks the chat model', function (): void {
    $values = TaskModelPreset::values(tmpModel('big'), tmpModel('small'));

    expect($values['task_model'])->toBe('small')
        ->and($values['eval_judge_model'])->toBe('big')
        ->and($values['translation_model'])->toBe('big')
        ->and($values['guardrails_model'])->toBeNull()
        ->and($values['images_model'])->toBeNull()
        ->and(array_keys($values))->toBe(['task_model', ...array_map(fn (LlmTask $task): string => $task->settingsProperty(), LlmTask::appTasks())]);
});

it('describes images with the model that reads images, and with none without one', function (): void {
    expect(TaskModelPreset::values(tmpModel('big'), tmpModel('small'), tmpModel('eyes'))['images_model'])->toBe('eyes')
        ->and(TaskModelPreset::values(tmpModel('big'), null, tmpModel('eyes'))['images_model'])->toBe('eyes')
        ->and(TaskModelPreset::values(tmpModel('big'), tmpModel('small'))['images_model'])->toBeNull();
});

it('sets nothing without a small model or a model that reads images', function (): void {
    expect(array_filter(TaskModelPreset::values(tmpModel('big'), null)))->toBe([]);
});
