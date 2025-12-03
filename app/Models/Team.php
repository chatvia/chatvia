<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Teams\Models\Team as JuragerTeam;

class Team extends JuragerTeam
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
     * Determine if the given user is a member of the team.
     */
    public function hasUserWithEmail(string $email): bool
    {
        return $this->allUsers()->contains(fn ($user) => $user->email === $email);
    }
}
