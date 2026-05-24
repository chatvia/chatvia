<?php

namespace App\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUsage extends Model
{
    protected $guarded = ['id'];

    public function credit(): BelongsTo
    {
        return $this->belongsTo(UserCredit::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
