<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    /**
     * Associated lead captured in the same conversation & agent.
     * Matched via conversation_id + agent_id.
     */
    public function lead(): HasOne
    {
        return $this
            ->hasOne(Lead::class, 'conversation_id', 'conversation_id')
            ->where('agent_id', 'messages.agent_id');
    }
}
