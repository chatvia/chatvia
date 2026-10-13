<?php

namespace Domain\Knowledge\Source\Qa\QueryBuilders;

use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Knowledge\Source\Qa\Services\QuestionNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queries over curated Q&A entries: the whole-question match that decides
 * whether a message is answered from a Q&A entry.
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

        return $this->where('agent_id', $agentId)
            ->whereIn('id', AgentQuestionAnswerQuestion::query()
                ->select('agent_question_answer_id')
                ->where('agent_id', $agentId)
                ->where('normalized', $normalized))
            ->orderBy('id')
            ->first();
    }
}
