<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatTopic extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_topic', 'chat_topic_id', 'agent_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'chat_topic_id');
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    public function scopeUserDefined(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function ($q) use ($user) {
            $q->whereNull('user_id')
                ->orWhere('user_id', $user->id);
        });
    }

    public function isSystem(): bool
    {
        return $this->user_id === null;
    }

    public function isUserDefined(): bool
    {
        return $this->user_id !== null;
    }
}
