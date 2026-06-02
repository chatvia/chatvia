<?php

namespace Domain\Agent\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per agent holding the ordered JSON array of conversation starters
 * (`text`). Same shape as a template's `starters` column, so read-through and
 * detach can move starters between the two without reshaping.
 *
 * @property int $id
 * @property int $agent_id
 * @property array<int, string> $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 *
 * @method static \Database\Factories\AgentStarterFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class AgentStarter extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'text' => 'array',
        ];
    }
}
