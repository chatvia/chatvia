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
 * model; image descriptions get the chat model when only it reads images.
 * Every other task has no model of its own, so it uses the shared one.
 *
 * Without a small model nothing is set: every task uses the chat model.
 */
final class TaskModelPreset
{
    /**
     * @return array<string, ?string> AiSettings property => model alias
     */
    public static function values(Llm $chat, ?Llm $small): array
    {
        $values = ['task_model' => $small?->external_name];

        foreach (LlmTask::appTasks() as $task) {
            $values[$task->settingsProperty()] = $small instanceof Llm ? self::own($task, $chat, $small) : null;
        }

        return $values;
    }

    /** The task's own model, or null when the shared small one suits it. */
    private static function own(LlmTask $task, Llm $chat, Llm $small): ?string
    {
        if ($task->tier() === ModelTier::Large) {
            return $chat->external_name;
        }

        if ($task === LlmTask::Images && ($small->features['vision'] ?? null) === false && ($chat->features['vision'] ?? null) !== false) {
            return $chat->external_name;
        }

        return null;
    }
}
