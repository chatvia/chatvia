<?php

namespace Domain\Prompt\Models;

use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * A reusable, account-level prompt the user authors once and attaches to many
 * agents (Settings → Prompts). Owned by the user; referenced by agents via
 * `agents.agent_prompt_id`.
 *
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property string $name
 * @property string $prompt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, Agent> $agents
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class AgentPrompt extends Model
{
    use HasExternalId, HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Agents currently using this library prompt.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }
}
