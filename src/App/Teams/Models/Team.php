<?php

namespace App\Teams\Models;

use App\Auth\Models\User;
use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Invitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read User $owner
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Team whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'name',
    'user_id',
])]
class Team extends Model
{
    use HasExternalId;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    public function hasUserWithEmail(string $email): bool
    {
        return $this->owner->email === $email
            || $this->members->contains(fn ($user): bool => $user->email === $email);
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
     * Get a user's role in this team.
     */
    public function userRole(User $user): ?Role
    {
        // Team owner doesn't have a role in the pivot table
        if ($this->user_id === $user->id) {
            return null;
        }

        $membership = $this->members()->where('users.id', $user->id)->first();

        if (! $membership) {
            return null;
        }

        return Role::find($membership->pivot->getAttribute('role_id'));
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }
}
