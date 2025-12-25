<?php

namespace App\Models;

use App\Traits\SupportsHmac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    use HasFactory, SupportsHmac;

    protected $guarded = ['id'];

    protected array $hmacEncryptionColumns = [
        'id',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'chat_session_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
