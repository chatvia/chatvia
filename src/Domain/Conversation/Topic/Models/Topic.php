<?php

namespace Domain\Conversation\Topic\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Collections\AgentCollection;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
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
 * @property-read Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|Topic forUser(\App\Identity\Workspace\Models\User $user)
 * @method static Builder<static>|Topic newModelQuery()
 * @method static Builder<static>|Topic newQuery()
 * @method static Builder<static>|Topic query()
 * @method static Builder<static>|Topic system()
 * @method static Builder<static>|Topic userDefined()
 * @method static Builder<static>|Topic whereCreatedAt($value)
 * @method static Builder<static>|Topic whereDescription($value)
 * @method static Builder<static>|Topic whereId($value)
 * @method static Builder<static>|Topic whereTopic($value)
 * @method static Builder<static>|Topic whereUpdatedAt($value)
 * @method static Builder<static>|Topic whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'chat_topics')]
class Topic extends Model
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
        return $this->hasMany(Conversation::class, 'chat_topic_id');
    }

    #[Scope]
    protected function system(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    #[Scope]
    protected function userDefined(Builder $query): void
    {
        $query->whereNotNull('user_id');
    }

    #[Scope]
    protected function forUser(Builder $query, User $user): void
    {
        $query->where(function ($q) use ($user): void {
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
