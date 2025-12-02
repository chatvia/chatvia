<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $guarded = [];

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
