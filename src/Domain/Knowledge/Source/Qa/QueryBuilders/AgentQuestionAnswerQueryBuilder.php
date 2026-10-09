<?php

namespace Domain\Knowledge\Source\Qa\QueryBuilders;

use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Queries over curated Q&A entries: the strict phrase search that decides
 * whether a message is answered from a Q&A entry.
 *
 * @template TModel of AgentQuestionAnswer
 *
 * @extends Builder<TModel>
 */
class AgentQuestionAnswerQueryBuilder extends Builder
{
    /**
     * Search Q&A entries using PostgreSQL full-text search with STRICT phrase matching.
     * Uses phraseto_tsquery for exact phrase matching (word order matters).
     *
     * @return list<\stdClass> matching rows (id, agent_id, title, questions, answer, rank), best first
     */
    public function phraseSearch(int $agentId, string $query, int $limit = 1): array
    {
        $sql = "
SELECT 
    qa.id, 
    qa.agent_id, 
    qa.title,
    qa.questions, 
    qa.answer,
    ts_rank(to_tsvector('simple', qa.questions), phraseto_tsquery('simple', ?)) AS rank
FROM agent_question_answers qa
WHERE qa.agent_id = ?
  AND to_tsvector('simple', qa.questions) @@ phraseto_tsquery('simple', ?)
ORDER BY rank DESC
LIMIT ?
";

        return DB::select($sql, [$query, $agentId, $query, $limit]);
    }
}
