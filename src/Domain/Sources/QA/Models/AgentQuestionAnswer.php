<?php

namespace Domain\Sources\QA\Models;

use Domain\Sources\QA\QueryBuilders\AgentQuestionAnswerQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Agents\Models\Agent;

class AgentQuestionAnswer extends Model
{
    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function newEloquentBuilder($query): AgentQuestionAnswerQueryBuilder
    {
        return new AgentQuestionAnswerQueryBuilder($query);
    }
}
