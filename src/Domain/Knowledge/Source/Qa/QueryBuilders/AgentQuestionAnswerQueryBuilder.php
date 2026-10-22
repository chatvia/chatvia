<?php

namespace Domain\Knowledge\Source\Qa\QueryBuilders;

use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Knowledge\Source\Qa\Services\QuestionNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queries over curated Q&A entries: the matches that decide whether a
 * message is answered from a Q&A entry, as a whole question or by meaning.
 *
 * @template TModel of AgentQuestionAnswer
 *
 * @extends Builder<TModel>
 */
class AgentQuestionAnswerQueryBuilder extends Builder
{
    /**
     * The agent's entry with a question equal to the message as a whole,
     * ignoring case, accents, punctuation and spacing ({@see QuestionNormalizer}).
     * A word or phrase that is only part of a question does not match.
     *
     * @return TModel|null
     */
    public function wholeQuestionMatch(int $agentId, string $message): ?AgentQuestionAnswer
    {
        $normalized = QuestionNormalizer::normalize($message);

        if ($normalized === '') {
            return null;
        }

        // The hash condition is what `agent_qa_questions_agent_normalized_idx`
        // (agent_id, md5(normalized)) serves; the text condition keeps a hash
        // collision from matching.
        return $this->where('agent_id', $agentId)
            ->whereIn('id', AgentQuestionAnswerQuestion::query()
                ->select('agent_question_answer_id')
                ->where('agent_id', $agentId)
                ->whereRaw('md5(normalized) = md5(?)', [$normalized])
                ->where('normalized', $normalized))
            ->orderBy('id')
            ->first();
    }

    /**
     * The agent's entry whose question is closest in meaning to the query,
     * when its cosine similarity reaches the threshold.
     *
     * @param  string  $queryVector  the query embedding as a pgvector literal
     * @param  string  $cast  the stored embedding type (`halfvec(1024)`, …)
     * @return TModel|null
     */
    public function closestInMeaning(int $agentId, string $queryVector, string $cast, float $threshold): ?AgentQuestionAnswer
    {
        $closest = AgentQuestionAnswerQuestion::query()
            ->where('agent_id', $agentId)
            ->whereNotNull('embedding')
            ->selectRaw("agent_question_answer_id, 1 - (embedding <=> ?::{$cast}) AS similarity", [$queryVector])
            ->orderByRaw("embedding <=> ?::{$cast}", [$queryVector])
            ->toBase()
            ->first();

        if ($closest === null || (float) $closest->similarity < $threshold) {
            return null;
        }

        return $this->where('agent_id', $agentId)->find((int) $closest->agent_question_answer_id);
    }
}
