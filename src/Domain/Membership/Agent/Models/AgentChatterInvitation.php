<?php

namespace Domain\Membership\Agent\Models;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A pending chatter invitation to an agent. One row per outstanding link;
 * deleted on accept. The `token` column carries the secret used in the
 * invitation URL. A link stays valid for {@see self::TTL_DAYS} days.
 *
 * @property int $id
 * @property int $agent_id
 * @property string $email
 * @property string $token
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 */
#[Guarded(['id'])]
class AgentChatterInvitation extends Model
{
    /** How many days an invitation link stays valid after it is sent. */
    public const int TTL_DAYS = 7;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
