<?php

namespace Domain\Knowledge\Source\Qa\Models;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\QueryBuilders\AgentQuestionAnswerQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A curated question-and-answer entry of one agent. When a visitor's message
 * matches one of its questions as a phrase, the chat answers with the stored
 * answer instead of the LLM. Questions are stored one per line.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $title
 * @property string $answer
 * @property string $questions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 *
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer newModelQuery()
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer newQuery()
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer phraseSearch(int $agentId, string $query, int $limit = 1)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer query()
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereAgentId($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereAnswer($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereCreatedAt($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereId($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereQuestions($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereTitle($value)
 * @method static AgentQuestionAnswerQueryBuilder<static>|AgentQuestionAnswer whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class AgentQuestionAnswer extends Model
{
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function newEloquentBuilder($query): AgentQuestionAnswerQueryBuilder
    {
        return new AgentQuestionAnswerQueryBuilder($query);
    }
}
