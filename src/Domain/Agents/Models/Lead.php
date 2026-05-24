<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Domain\Chat\Models\ChatConversation;
use Domain\Chat\Models\ChatMessage;

class Lead extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'contacted_at' => 'datetime',
    ];

    // Relationships
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id', 'chat_conversation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(\App\Auth\Models\User::class, 'assigned_to');
    }
}
