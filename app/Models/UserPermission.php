<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    protected $guarded = ['id'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
