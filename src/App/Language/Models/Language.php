<?php

namespace App\Language\Models;

use Domain\Chat\Models\ChatConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    protected $fillable = [
        'name',
        'native',
        'code',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }
}
