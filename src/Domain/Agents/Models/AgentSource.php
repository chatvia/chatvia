<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AgentSource extends MorphPivot
{
    protected $table = 'agent_sources';

    protected $guarded = ['id'];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
