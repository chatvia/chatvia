<?php

namespace App\Models;

use App\QueryBuilders\ChatMessageQueryBuilder;
use App\Traits\SupportsExternalId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMessage extends Model
{
    use HasFactory, SupportsExternalId;

    protected $fillable = [
        'external_id',
        'chat_conversation_id',
        'llm_id',
        'agent_question_answer_id',
        'generation_id',
        'message_credits',
        'source',
        'content',
        'metadata',
        'role',
        'confidence_score',
        'sentiment',
        'tone',
        'feedback',
        'is_response_from_qa',
        'llm_found_answer',
        'response_time_ms',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'is_usage_counted',
        'expires_at',
        'read_at',
        'created_at',
        'updated_at',
    ];

    protected array $hmacEncryptionColumns = [
        'conversation.session.agent_id',
        'conversation_id',
        'id',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'float',
            'metadata' => 'array',
            'read_at' => 'datetime',
            'llm_found_answer' => 'boolean',
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

    /**
     * Q&A entry linked to this message (when the response has been revised).
     */
    public function questionAnswer(): BelongsTo
    {
        return $this->belongsTo(AgentQuestionAnswer::class, 'agent_question_answer_id');
    }

    public function newEloquentBuilder($query): ChatMessageQueryBuilder
    {
        return new ChatMessageQueryBuilder($query);
    }
}
