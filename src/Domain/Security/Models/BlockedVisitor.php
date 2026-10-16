<?php

declare(strict_types=1);

namespace Domain\Security\Models;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\BlockedVisitorFactory;
use Domain\Agent\Models\Agent;
use Domain\Security\Services\VisitorFingerprint;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;
use Support\Localization\Country\Models\Country;

/**
 * An anonymous visitor a workspace admin blocked from chatting with the
 * workspace's agents, usually after repeated attacks seen on the Guardrails
 * page. The visitor is known only by their fingerprint
 * ({@see VisitorFingerprint}); the browser, system and country are a snapshot
 * for the list, never the network address. A block ends at `expires_at`, or
 * never when that is null.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string $visitor_key
 * @property int|null $agent_id
 * @property string|null $browser
 * @property string|null $os
 * @property int|null $country_id
 * @property string|null $reason
 * @property int|null $blocked_by_user_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Agent|null $agent
 * @property-read Country|null $country
 * @property-read User|null $blockedBy
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class BlockedVisitor extends Model
{
    /** @use HasFactory<BlockedVisitorFactory> */
    use HasExternalId, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /**
     * Blocks still in force: no end, or an end still to come.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where(fn (Builder $query): Builder => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Shape one block for the Security page's list. The relations are
     * eager-loaded by the caller.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'external_id' => $this->external_id,
            'visitor' => substr($this->visitor_key, 0, 6),
            'browser' => $this->browser,
            'os' => $this->os,
            'country' => $this->country?->localizedName(),
            'agent_name' => $this->agent?->name,
            'reason' => $this->reason,
            'blocked_by' => $this->blockedBy?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
