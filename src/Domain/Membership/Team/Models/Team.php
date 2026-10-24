<?php

namespace Domain\Membership\Team\Models;

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Models\WorkspaceMembership;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Support\Concerns\HasExternalId;

/**
 * A team inside a workspace: a group of agents plus the teammates who work on
 * them. The workspace's owner holds every permission without a pivot row;
 * everyone else joins through `team_user` with exactly one {@see Role}.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Invitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read User|null $owner
 * @property-read Workspace $workspace
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'workspace_id',
])]
#[RouteKey('external_id')]
class Team extends Model
{
    use HasExternalId;

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * The owner of the team's workspace, who holds every team permission.
     *
     * @return HasOneThrough<User, WorkspaceMembership, $this>
     */
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, WorkspaceMembership::class, 'workspace_id', 'id', 'workspace_id', 'user_id')
            ->where('workspace_user.role', WorkspaceRole::Owner->value);
    }

    /**
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Roles a member can be invited into or moved to — every role except `owner`.
     *
     * @return HasMany<Role, $this>
     */
    public function invitableRoles(): HasMany
    {
        return $this->roles()->where('code', '!=', Role::OWNER_CODE);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'team_user')
            ->withPivot('role_id');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Whether the owner or any member already uses this email address,
     * compared without case.
     */
    public function hasUserWithEmail(string $email): bool
    {
        $email = Str::lower($email);

        return Str::lower($this->owner->email) === $email
            || $this->members->contains(fn ($user): bool => Str::lower($user->email) === $email);
    }

    /**
     * Check if the team has a specific user.
     */
    public function hasUser(User $user): bool
    {
        return $this->owner->id === $user->id
            || $this->members->contains($user);
    }

    /**
     * Update a team member's role.
     */
    public function updateMember(User $user, string $roleCode): void
    {
        $role = $this->roles()->where('code', $roleCode)->firstOrFail();
        $this->members()->updateExistingPivot($user->id, ['role_id' => $role->id]);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }
}
