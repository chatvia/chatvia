<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jurager\Teams\Models\Invitation as BaseInvitation;
use Jurager\Teams\Models\Role;

class Invitation extends BaseInvitation
{
    /**
     * Get the role associated with the invitation.
     * Override to fix the incorrect foreign key in the package.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
