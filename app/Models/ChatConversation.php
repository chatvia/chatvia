<?php

namespace App\Models;

use App\QueryBuilders\ChatConversationQueryBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'handoff_at' => 'datetime',
        ];
    }

    public function newEloquentBuilder($query): ChatConversationQueryBuilder
    {
        return new ChatConversationQueryBuilder($query);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ChatTopic::class, 'chat_topic_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'chat_conversation_id');
    }

    public function agent(): HasOneThrough
    {
        return $this->hasOneThrough(
            Agent::class,
            ChatSession::class,
            'id',
            'id',
            'chat_session_id',
            'agent_id'
        );
    }

    /**
     * The admin user who has taken over this conversation (handoff).
     */
    public function handoffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handoff_user_id');
    }

    /**
     * Check if conversation is currently controlled by a human admin.
     */
    public function isHandedOff(): bool
    {
        return $this->handoff_status === 'human';
    }

    /**
     * Check if AI should respond to this conversation.
     */
    public function shouldAiRespond(): bool
    {
        return in_array($this->handoff_status, ['ai', 'returned', null]);
    }
}
