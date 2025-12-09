<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function members(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class, 'team_user')
            ->withPivot('role_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function hasUserWithEmail(string $email): bool
    {
        return $this->owner->email === $email
            || $this->members->contains(fn ($user) => $user->email === $email);
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

        return Role::find($membership->pivot->role_id);
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }
}
