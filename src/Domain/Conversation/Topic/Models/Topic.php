<?php

namespace Domain\Conversation\Topic\Models;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A subject a conversation can be about ("Billing", "Shipping", ...). The
 * conversation analysis tags each conversation with the topics of its agent
 * (see {@see ConversationTopic}), and the chat log and topic trends read those
 * tags. A topic without a `workspace_id` is a system topic shared by every
 * workspace and cannot be edited; one with a `workspace_id` belongs to that
 * workspace. Agents choose their topics through the `agent_topic` pivot.
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property string $topic
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Agent> $agents
 * @property-read int|null $agents_count
 * @property-read Collection<int, Conversation> $conversations
 * @property-read int|null $conversations_count
 * @property-read Workspace|null $workspace
 *
 * @method static Builder<static>|Topic forWorkspace(\App\Identity\Workspace\Models\Workspace $workspace)
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
 * @method static Builder<static>|Topic whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class Topic extends Model
{
    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsToMany<Agent, $this> */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_topic');
    }

    /** @return BelongsToMany<Conversation, $this> */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_topics', 'topic_id', 'chat_conversation_id');
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function system(Builder $query): void
    {
        $query->whereNull('workspace_id');
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function userDefined(Builder $query): void
    {
        $query->whereNotNull('workspace_id');
    }

    /**
     * The topics a workspace can use: the system topics and its own.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forWorkspace(Builder $query, Workspace $workspace): void
    {
        $query->where(function ($q) use ($workspace): void {
            $q->whereNull('workspace_id')
                ->orWhere('workspace_id', $workspace->id);
        });
    }

    public function isSystem(): bool
    {
        return $this->workspace_id === null;
    }

    public function isUserDefined(): bool
    {
        return $this->workspace_id !== null;
    }
}
