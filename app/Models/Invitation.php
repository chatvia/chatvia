<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'team_id',
        'role_id',
        'email',
    ];

    /**
     * Get the team this invitation is for.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the role this invitation is for.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
