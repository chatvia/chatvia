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

    /**
     * Get the team owner.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user that owns the team (alias for owner).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the agents that belong to the team.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the roles for this team.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get all users in this team (including owner).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role_id', 'is_owner')
            ->withTimestamps();
    }

    /**
     * Get only the members (not the owner).
     */
    public function members(): BelongsToMany
    {
        return $this->users()->wherePivot('is_owner', false);
    }

    /**
     * Get all users (alias for users() - kept for backward compatibility).
     */
    public function allUsers()
    {
        return $this->users;
    }

    /**
     * Get invitations for this team.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Check if a user with the given email is already a member.
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(fn ($user) => $user->email === $email);
    }

    /**
     * Check if the team has a specific user.
     */
    public function hasUser(User $user): bool
    {
        return $this->allUsers()->contains($user);
    }

    /**
     * Update a team member's role.
     */
    public function updateUser(User $user, string $roleCode): void
    {
        $role = $this->roles()->where('code', $roleCode)->firstOrFail();
        $this->users()->updateExistingPivot($user->id, ['role_id' => $role->id]);
    }

    /**
     * Get a user's role in this team.
     */
    public function userRole(User $user): ?Role
    {
        $membership = $this->users()->where('users.id', $user->id)->first();

        if (! $membership) {
            return null;
        }

        return Role::find($membership->pivot->role_id);
    }
}
