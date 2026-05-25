<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 * @property-read Model|\Eloquent $sourceable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'agent_sources')]
class AgentSource extends MorphPivot
{
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
