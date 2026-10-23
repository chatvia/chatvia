<?php

use App\Admin\Settings\TaskModelPreset;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Models\Llm;

/**
 * TaskModelPreset: the small model does every quick task, the tasks that
 * need a large model get the chat model, and image descriptions get the
 * model that reads images, or none. A setting holds the model's id, as
 * text.
 */
function tmpModel(int $id): Llm
{
    return new Llm()->forceFill(['id' => $id]);
}

it('gives quick tasks the small model and large tasks the chat model', function (): void {
    $values = TaskModelPreset::values(tmpModel(1), tmpModel(2));

    expect($values['task_model'])->toBe('2')
        ->and($values['eval_judge_model'])->toBe('1')
        ->and($values['translation_model'])->toBe('1')
        ->and($values['guardrails_model'])->toBeNull()
        ->and($values['images_model'])->toBeNull()
        ->and(array_keys($values))->toBe(['task_model', ...array_map(fn (LlmTask $task): string => $task->settingsProperty(), LlmTask::appTasks())]);
});

it('describes images with the model that reads images, and with none without one', function (): void {
    expect(TaskModelPreset::values(tmpModel(1), tmpModel(2), tmpModel(3))['images_model'])->toBe('3')
        ->and(TaskModelPreset::values(tmpModel(1), null, tmpModel(3))['images_model'])->toBe('3')
        ->and(TaskModelPreset::values(tmpModel(1), tmpModel(2))['images_model'])->toBeNull();
});

it('sets nothing without a small model or a model that reads images', function (): void {
    expect(array_filter(TaskModelPreset::values(tmpModel(1), null)))->toBe([]);
});
