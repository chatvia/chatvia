<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Observers;

use Domain\Knowledge\Source\Qa\Actions\SyncQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * Keeps the question rows of a curated Q&A entry in step with its
 * `questions`, however the entry is saved (dashboard, Chat log revise, API).
 */
class AgentQuestionAnswerObserver
{
    public function __construct(
        private readonly SyncQaQuestionsAction $syncQuestions,
    ) {}

    public function saved(AgentQuestionAnswer $qa): void
    {
        if ($qa->wasRecentlyCreated || $qa->wasChanged('questions')) {
            $this->syncQuestions->execute($qa);
        }
    }
}
