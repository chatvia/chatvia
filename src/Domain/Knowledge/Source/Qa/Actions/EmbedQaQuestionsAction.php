<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Actions;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;

/**
 * Embeds the questions of a curated Q&A entry that have no embedding yet, so
 * a visitor's message can match one by meaning. The questions are short, so
 * one batch call embeds them all.
 */
class EmbedQaQuestionsAction
{
    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
    ) {}

    public function execute(AgentQuestionAnswer $qa): void
    {
        $rows = AgentQuestionAnswerQuestion::query()
            ->where('agent_question_answer_id', $qa->id)
            ->whereNull('embedding')
            ->get(['id', 'question']);

        if ($rows->isEmpty()) {
            return;
        }

        $vectors = $this->embeddings->embedBatch($rows->pluck('question')->all());

        foreach ($rows->values() as $index => $row) {
            if (isset($vectors[$index])) {
                $row->update(['embedding' => '['.implode(',', $vectors[$index]).']']);
            }
        }
    }
}
