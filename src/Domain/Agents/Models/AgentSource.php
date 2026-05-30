<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $agent_id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property int $retrieval_count
 * @property string|null $last_retrieved_at
 * @property-read Agent $agent
 * @property-read Model|\Eloquent $sourceable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableType($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'agent_sources')]
class AgentSource extends MorphPivot
{
    public $timestamps = false;

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ids of `sourceable_id` rows of `$type` attached only to `$agentId` — i.e.
     * sources this agent owns exclusively. The agent-deleting cleanup uses this
     * to know which sources to fully delete vs. just detach.
     *
     * @return Collection<int, int>
     */
    public static function exclusiveSourceIds(int $agentId, string $type): Collection
    {
        return DB::table('agent_sources as a')
            ->select('a.sourceable_id')
            ->where('a.agent_id', $agentId)
            ->where('a.sourceable_type', $type)
            ->whereNotExists(function ($q) use ($type, $agentId): void {
                $q->select(DB::raw(1))
                    ->from('agent_sources as b')
                    ->whereColumn('b.sourceable_id', 'a.sourceable_id')
                    ->where('b.sourceable_type', $type)
                    ->where('b.agent_id', '!=', $agentId);
            })
            ->pluck('a.sourceable_id');
    }
}
