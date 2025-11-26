<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatTopic extends Model
{
    protected $guarded = ['id'];

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_topics');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'chat_topic_id');
    }
}
