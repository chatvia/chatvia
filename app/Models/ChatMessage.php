<?php

namespace App\Models;

use App\QueryBuilders\ChatMessageQueryBuilder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function llm(): BelongsTo
    {
        return $this->belongsTo(Llm::class);
    }

    /**
     * Associated lead captured in the same conversation & agent.
     * Matched via conversation_id + agent_id.
     */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'chat_conversation_id', 'chat_conversation_id');
    }

    public function newEloquentBuilder($query): ChatMessageQueryBuilder
    {
        return new ChatMessageQueryBuilder($query);
    }
}
