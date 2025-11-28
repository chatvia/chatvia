<?php

namespace App\Models;

use App\QueryBuilders\ChatConversationQueryBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatConversation extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

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

    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'chat_conversation_id');
    }
}
