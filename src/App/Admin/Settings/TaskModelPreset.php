<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\ModelTier;
use Domain\Llm\Models\Llm;

/**
 * The task models a provider's preset gives (Settings → AI models →
 * Models for app tasks), for the setup and for "Use the recommended
 * models": the small model is the model for quick tasks (`task_model`);
 * the tasks that need a large model ({@see LlmTask::tier()}) get the chat
 * model; image descriptions get the model that reads images. Every other
 * task has no model of its own, so it uses the shared one.
 *
 * Without a small model, quick tasks use the chat model. Without a model
 * that reads images, image descriptions have none (the caller turns them
 * off).
 */
final class TaskModelPreset
{
    /**
     * @return array<string, ?string> AiSettings property => model alias
     */
    public static function values(Llm $chat, ?Llm $small, ?Llm $vision = null): array
    {
        $values = ['task_model' => $small?->external_name];

        foreach (LlmTask::appTasks() as $task) {
            $values[$task->settingsProperty()] = match (true) {
                $task === LlmTask::Images => $vision?->external_name,
                $small instanceof Llm && $task->tier() === ModelTier::Large => $chat->external_name,
                default => null,
            };
        }

        return $values;
    }

    /** Whether the preset leaves the task to the model for quick tasks. */
    public static function isQuickTask(LlmTask $task): bool
    {
        return $task !== LlmTask::Chat && $task !== LlmTask::Images && $task->tier() === ModelTier::Small;
    }
}
