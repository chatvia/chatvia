<?php

namespace Domain\Agent\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per agent holding the ordered JSON array of conversation starters.
 * Each entry is `{ text, icon }` where `icon` is an optional Heroicons (outline)
 * export name (e.g. `ChatBubbleLeftRightIcon`) or null for a text-only chip.
 *
 * @property int $id
 * @property int $agent_id
 * @property array<int, array{text: string, icon: string|null}> $starters
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
            'starters' => 'array',
        ];
    }
}
