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
 * invitation URL — it replaces the previous signed-URL approach.
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
class AgentMemberInvitation extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
