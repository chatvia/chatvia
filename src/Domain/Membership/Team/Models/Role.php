<?php

namespace Domain\Membership\Team\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;
use Support\Concerns\HasExternalId;

/**
 * A team role: a named bundle of permissions plus the agents it may reach
 * (`agent_role` pivot, or every agent through {@see Permission::ACCESS_ALL_AGENTS}).
 * Each teammate holds one role per team through the `team_user.role_id` pivot
 * column. The team owner has no pivot row and holds everything implicitly.
 *
 * `code` is a stable slug: the default roles use the keys of
 * config('team-permissions.roles'); custom roles derive it from their name.
 *
 * @property int $id
 * @property string|null $external_id
 * @property int|null $team_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Team|null $team
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereGuardName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Role withoutPermission($permissions)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'code',
    'name',
    'description',
    'team_id',
    'guard_name',
])]
class Role extends SpatieRole
{
    use HasExternalId;

    /** Code of the default role every team is created with; it can never be edited or deleted. */
    public const string OWNER_CODE = 'owner';

    /** Code of the legacy admin role; like the owner role it can never be deleted. */
    public const string ADMIN_CODE = 'admin';

    public function isOwnerRole(): bool
    {
        return $this->code === self::OWNER_CODE;
    }

    /**
     * Whether the role grants every agent in the team, either because it is the
     * owner role or because it carries the all-agents meta-permission.
     */
    public function grantsAllAgents(): bool
    {
        return $this->isOwnerRole() || $this->hasPermissionTo(Permission::ACCESS_ALL_AGENTS);
    }

    /**
     * The owner and admin roles hold the team together, so they can never be deleted.
     */
    public function isProtected(): bool
    {
        return in_array($this->code, [self::OWNER_CODE, self::ADMIN_CODE], true);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The agents this role reaches when it does not grant all agents.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class);
    }
}
