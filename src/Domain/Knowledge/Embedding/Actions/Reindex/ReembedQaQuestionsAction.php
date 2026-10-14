<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Actions\Reindex;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\DB;

/**
 * Embeds the Q&A questions in one id range again with the embedding model in
 * use, so a visitor's message still matches them by meaning. One part of a
 * re-index ({@see StartEmbeddingReindexAction}).
 */
class ReembedQaQuestionsAction
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
    ) {}

    /**
     * @return int how many questions were embedded again
     */
    public function execute(int $firstId, int $lastId): int
    {
        $questions = AgentQuestionAnswerQuestion::query()
            ->whereBetween('id', [$firstId, $lastId])
            ->orderBy('id')
            ->get(['id', 'question'])
            ->values();

        if ($questions->isEmpty()) {
            return 0;
        }

        $vectors = $this->embeddings->embedBatch($questions->pluck('question')->all());

        DB::transaction(function () use ($questions, $vectors): void {
            foreach ($questions as $position => $question) {
                AgentQuestionAnswerQuestion::query()
                    ->whereKey($question->id)
                    ->update(['embedding' => '['.implode(',', $vectors[$position]).']']);
            }
        });

        return $questions->count();
    }
}
