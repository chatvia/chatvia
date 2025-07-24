<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Credit extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function usage(): HasOne
    {
        return $this->hasOne(Usage::class);
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
