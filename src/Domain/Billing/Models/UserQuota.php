<?php

namespace Domain\Billing\Models;

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A workspace's plan limits (agents, pages, seats, …) plus the extra amounts
 * its add-on subscriptions add.
 *
 * @property int $id
 * @property int $user_id
 * @property int $agents_limit
 * @property int $pages_limit
 * @property int $links_limit
 * @property int $minutes_limit
 * @property int $custom_domains_limit
 * @property int $workspace_users_limit
 * @property int $teams_limit
 * @property int $extra_agents_limit
 * @property int $extra_custom_domains_limit
 * @property int $extra_members_limit
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereAgentsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereCustomDomainsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereLinksLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereWorkspaceUsersLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereMinutesLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota wherePagesLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereTeamsLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserQuota whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[WithoutTimestamps]
class UserQuota extends Model
{
    /**
     * Effective agents limit — plan quota plus any active extra-agents
     * subscription quantity. Use for capacity checks instead of the raw
     * `agents_limit`.
     */
    public function totalAgentsLimit(): int
    {
        return (int) $this->agents_limit + (int) $this->extra_agents_limit;
    }

    /**
     * Effective custom-domains limit — plan quota plus any active
     * custom-domain subscription quantity.
     */
    public function totalCustomDomainsLimit(): int
    {
        return (int) $this->custom_domains_limit + (int) $this->extra_custom_domains_limit;
    }

    /**
     * Effective workspace-users (seats) limit — plan quota plus any active
     * Members subscription quantity. The bucket is shared by team members and
     * chatters; use for seat gates instead of the raw `workspace_users_limit`.
     */
    public function totalWorkspaceUsersLimit(): int
    {
        return (int) $this->workspace_users_limit + (int) $this->extra_members_limit;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
