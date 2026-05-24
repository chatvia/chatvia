<?php

namespace App\Changelog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogItem extends Model
{
    protected $fillable = [
        'changelog_id',
        'type',
        'description',
    ];

    public function changelog(): BelongsTo
    {
        return $this->belongsTo(Changelog::class);
    }
}
