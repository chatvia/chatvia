<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Changelog extends Model
{
    protected $fillable = [
        'version',
        'release_date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ChangelogItem::class);
    }

    protected function casts(): array
    {
        return [
            'release_date' => 'datetime',
        ];
    }
}
