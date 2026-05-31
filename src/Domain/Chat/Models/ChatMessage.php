<?php

namespace Domain\Chat\Models;

use Domain\Agents\Models\Lead;
use Domain\Agents\Models\Llm;
use Domain\Chat\QueryBuilders\ChatMessageQueryBuilder;
use Domain\Sources\QA\Models\AgentQuestionAnswer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Support\Traits\SupportsExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int $chat_conversation_id
 * @property int|null $llm_id
 * @property int|null $agent_question_answer_id
 * @property string|null $generation_id
 * @property int $message_credits
 * @property string|null $source
 * @property string|null $content
 * @property array<array-key, mixed>|null $metadata
 * @property string $role
 * @property float|null $confidence_score
 * @property string|null $sentiment
 * @property string|null $tone
 * @property string|null $feedback
 * @property bool|null $is_response_from_qa
 * @property bool|null $has_llm_found_answer
 * @property int|null $response_time_ms
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $cost_in_micro_cents
 * @property bool|null $is_usage_counted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $expires_at
 * @property Carbon|null $read_at
 * @property-read ChatConversation|null $conversation
 * @property-read Lead|null $lead
 * @property-read Llm|null $llm
 * @property-read AgentQuestionAnswer|null $questionAnswer
 *
 * @method static \Database\Factories\ChatMessageFactory factory($count = null, $state = [])
 * @method static ChatMessageQueryBuilder<static>|ChatMessage forAgentConversation(int $agentId, string $conversationId)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage newModelQuery()
 * @method static ChatMessageQueryBuilder<static>|ChatMessage newQuery()
 * @method static ChatMessageQueryBuilder<static>|ChatMessage query()
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereAgentQuestionAnswerId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereChatConversationId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereCompletionTokens($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereConfidenceScore($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereContent($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereCostInMicroCents($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereCreatedAt($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereExpiresAt($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereExternalId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereFeedback($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereGenerationId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereIsResponseFromQa($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereIsUsageCounted($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereHasLlmFoundAnswer($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereLlmId($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereMessageCredits($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereMetadata($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage wherePromptTokens($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereReadAt($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereResponseTimeMs($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereRole($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereSentiment($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereSource($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereTone($value)
 * @method static ChatMessageQueryBuilder<static>|ChatMessage whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
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
    'has_llm_found_answer',
    'response_time_ms',
    'prompt_tokens',
    'completion_tokens',
    'cost_in_micro_cents',
    'is_usage_counted',
    'expires_at',
    'read_at',
    'created_at',
    'updated_at',
])]
class ChatMessage extends Model
{
    use HasFactory, SupportsExternalId;

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
            'has_llm_found_answer' => 'boolean',
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
