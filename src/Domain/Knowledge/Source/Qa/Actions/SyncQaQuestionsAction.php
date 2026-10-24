<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Actions;

use Domain\Knowledge\Source\Qa\Jobs\EmbedQaQuestionsJob;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Knowledge\Source\Qa\Services\QuestionNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds the question rows of a curated Q&A entry from its `questions`
 * (one per line): one row per non-empty question, with its normalized form,
 * and queues their embeddings for the match by meaning. Questions that
 * normalize to nothing (only punctuation) are left out, so they never match.
 */
class SyncQaQuestionsAction
{
    public function execute(AgentQuestionAnswer $qa): void
    {
        $rows = Str::of($qa->questions)->split('/\R/u')
            ->map(fn (string $question): string => Str::trim($question))
            ->filter(fn (string $question): bool => $question !== '')
            ->map(fn (string $question): array => [
                'agent_question_answer_id' => $qa->id,
                'agent_id' => $qa->agent_id,
                'question' => $question,
                'normalized' => QuestionNormalizer::normalize($question),
            ])
            ->filter(fn (array $row): bool => $row['normalized'] !== '')
            ->unique('normalized')
            ->values()
            ->all();

        DB::transaction(function () use ($qa, $rows): void {
            AgentQuestionAnswerQuestion::query()->where('agent_question_answer_id', $qa->id)->delete();
            AgentQuestionAnswerQuestion::query()->insert($rows);
        });

        if ($rows !== []) {
            EmbedQaQuestionsJob::dispatch($qa)->afterCommit();
        }
    }
}
