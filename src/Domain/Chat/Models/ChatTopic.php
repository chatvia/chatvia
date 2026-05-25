<?php

namespace Domain\Chat\Models;

use App\Auth\Models\User;
use Domain\Agents\Collections\AgentCollection;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $topic
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentCollection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, ChatConversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|ChatTopic forUser(\App\Auth\Models\User $user)
 * @method static Builder<static>|ChatTopic newModelQuery()
 * @method static Builder<static>|ChatTopic newQuery()
 * @method static Builder<static>|ChatTopic query()
 * @method static Builder<static>|ChatTopic system()
 * @method static Builder<static>|ChatTopic userDefined()
 * @method static Builder<static>|ChatTopic whereCreatedAt($value)
 * @method static Builder<static>|ChatTopic whereDescription($value)
 * @method static Builder<static>|ChatTopic whereId($value)
 * @method static Builder<static>|ChatTopic whereTopic($value)
 * @method static Builder<static>|ChatTopic whereUpdatedAt($value)
 * @method static Builder<static>|ChatTopic whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class ChatTopic extends Model
{
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
        return $query->where(function ($q) use ($user): void {
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
