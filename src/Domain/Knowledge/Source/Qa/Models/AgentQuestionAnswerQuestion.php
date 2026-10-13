<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question of a curated Q&A entry, kept apart so a visitor's message can
 * be matched against each question on its own: as a whole question (by its
 * normalized form) or by meaning (by its embedding). The rows are rebuilt from
 * the entry's `questions` (SyncQaQuestionsAction).
 *
 * @property int $id
 * @property int $agent_question_answer_id
 * @property int $agent_id
 * @property string $question
 * @property string $normalized
 * @property string|null $embedding
 * @property-read AgentQuestionAnswer $answerEntry
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class AgentQuestionAnswerQuestion extends Model
{
    /** @return BelongsTo<AgentQuestionAnswer, $this> */
    public function answerEntry(): BelongsTo
    {
        return $this->belongsTo(AgentQuestionAnswer::class, 'agent_question_answer_id');
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
