<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Services;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;

/**
 * Finds the curated Q&A entry that answers a message, if any:
 *
 *  1. a saved question equal to the message as a whole (ignoring case,
 *     accents and punctuation), or to its search question;
 *  2. else the saved question closest in meaning to the search question,
 *     when it is very close (a high bar, as the saved answer is sent word for
 *     word). Only for an embedding model whose bar was measured.
 */
class QaMatcher
{
    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
        private readonly EmbeddingStorage $storage,
    ) {}

    /**
     * @param  string  $message  the visitor's message (PII masked)
     * @param  string  $searchQuery  the full question the turn searches with
     */
    public function match(Agent $agent, string $message, string $searchQuery): ?AgentQuestionAnswer
    {
        $whole = AgentQuestionAnswer::query()->wholeQuestionMatch($agent->id, $message)
            ?? ($searchQuery !== $message ? AgentQuestionAnswer::query()->wholeQuestionMatch($agent->id, $searchQuery) : null);

        if ($whole instanceof AgentQuestionAnswer) {
            return $whole;
        }

        $threshold = RelevanceThresholds::qaSimilarity();

        // No measured bar, or nothing embedded to compare with: no embedding call.
        if ($threshold === null || ! AgentQuestionAnswerQuestion::query()->where('agent_id', $agent->id)->whereNotNull('embedding')->exists()) {
            return null;
        }

        $vector = '['.implode(',', $this->embeddings->embedQuery($searchQuery)).']';

        return AgentQuestionAnswer::query()->closestInMeaning($agent->id, $vector, $this->storage->columnType(), $threshold);
    }
}
