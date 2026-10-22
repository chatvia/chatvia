<?php

namespace Domain\Agent\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The pivot that attaches a knowledge source (file, website, text, YouTube
 * video) to an agent, with how often answers retrieved it. A source can be
 * shared by several agents of one workspace.
 *
 * @property int $agent_id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property int $retrieval_count
 * @property Carbon|null $last_retrieved_at
 * @property-read Agent $agent
 * @property-read Model|\Eloquent $sourceable
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereAgentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereLastRetrievedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereRetrievalCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentSource whereSourceableType($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'agent_sources')]
#[WithoutTimestamps]
class AgentSource extends MorphPivot
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_retrieved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** @return MorphTo<Model, $this> */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ids of `sourceable_id` rows of `$type` attached only to `$agentId` — i.e.
     * sources this agent owns exclusively. The agent purge uses this to know
     * which sources to fully delete vs. just detach.
     *
     * Another agent that is itself deleted (waiting for its own purge) does
     * not count: otherwise two agents deleted together would each keep the
     * source they shared for the other, and it would outlive both.
     *
     * `$type` accepts a model FQCN or a morph alias; the column always stores
     * the enforced morph alias, so an FQCN is resolved before comparing.
     *
     * @return Collection<int, int>
     */
    public static function exclusiveSourceIds(int $agentId, string $type): Collection
    {
        $type = class_exists($type) ? (new $type)->getMorphClass() : $type;

        return DB::table('agent_sources as a')
            ->select('a.sourceable_id')
            ->where('a.agent_id', $agentId)
            ->where('a.sourceable_type', $type)
            ->whereNotExists(function ($q) use ($type, $agentId): void {
                $q->select(DB::raw(1))
                    ->from('agent_sources as b')
                    ->join('agents as other', 'other.id', '=', 'b.agent_id')
                    ->whereColumn('b.sourceable_id', 'a.sourceable_id')
                    ->where('b.sourceable_type', $type)
                    ->where('b.agent_id', '!=', $agentId)
                    ->whereNull('other.deleted_at');
            })
            ->pluck('a.sourceable_id');
    }
}
