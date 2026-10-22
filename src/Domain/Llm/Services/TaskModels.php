<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Models\Llm;

/**
 * The catalog model behind each {@see LlmTask}: the one picked for the task
 * in Settings → AI while it may be used, else the model for quick tasks, else
 * the chat model, else the first model of the catalog that may be used.
 */
class TaskModels
{
    /** The config key of the model every app task uses unless it has its own. */
    public const string SHARED_KEY = 'chatvia.ai.tasks.default';

    /**
     * @throws NoLlmAvailableException
     */
    public function model(LlmTask $task): Llm
    {
        return $this->find($task) ?? throw new NoLlmAvailableException;
    }

    /** The task's model, or null when the catalog has none that may be used. */
    public function find(LlmTask $task): ?Llm
    {
        if ($task === LlmTask::Chat) {
            return $this->picked($task->configKey()) ?? Llm::available()->first();
        }

        return $this->picked($task->configKey())
            ?? $this->picked(self::SHARED_KEY)
            ?? $this->picked(LlmTask::Chat->configKey())
            ?? Llm::available()->first();
    }

    private function picked(string $configKey): ?Llm
    {
        $model = Llm::fromSetting(config($configKey));

        return $model?->isAvailable() ? $model : null;
    }
}
