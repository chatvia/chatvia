<?php

namespace Domain\Conversation\Models;

use Domain\Conversation\QueryBuilders\MessageQueryBuilder;
use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;
use Domain\Lead\Models\Lead;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property Carbon|null $read_at
 * @property-read Conversation|null $conversation
 * @property-read Lead|null $lead
 * @property-read Llm|null $llm
 * @property-read AgentQuestionAnswer|null $questionAnswer
 *
 * @method static \Database\Factories\MessageFactory factory($count = null, $state = [])
 * @method static MessageQueryBuilder<static>|Message forAgentConversation(int $agentId, string $conversationId)
 * @method static MessageQueryBuilder<static>|Message newModelQuery()
 * @method static MessageQueryBuilder<static>|Message newQuery()
 * @method static MessageQueryBuilder<static>|Message query()
 * @method static MessageQueryBuilder<static>|Message whereAgentQuestionAnswerId($value)
 * @method static MessageQueryBuilder<static>|Message whereConversationId($value)
 * @method static MessageQueryBuilder<static>|Message whereCompletionTokens($value)
 * @method static MessageQueryBuilder<static>|Message whereConfidenceScore($value)
 * @method static MessageQueryBuilder<static>|Message whereContent($value)
 * @method static MessageQueryBuilder<static>|Message whereCostInMicroCents($value)
 * @method static MessageQueryBuilder<static>|Message whereCreatedAt($value)
 * @method static MessageQueryBuilder<static>|Message whereExternalId($value)
 * @method static MessageQueryBuilder<static>|Message whereFeedback($value)
 * @method static MessageQueryBuilder<static>|Message whereGenerationId($value)
 * @method static MessageQueryBuilder<static>|Message whereId($value)
 * @method static MessageQueryBuilder<static>|Message whereIsResponseFromQa($value)
 * @method static MessageQueryBuilder<static>|Message whereIsUsageCounted($value)
 * @method static MessageQueryBuilder<static>|Message whereHasLlmFoundAnswer($value)
 * @method static MessageQueryBuilder<static>|Message whereLlmId($value)
 * @method static MessageQueryBuilder<static>|Message whereMessageCredits($value)
 * @method static MessageQueryBuilder<static>|Message whereMetadata($value)
 * @method static MessageQueryBuilder<static>|Message wherePromptTokens($value)
 * @method static MessageQueryBuilder<static>|Message whereReadAt($value)
 * @method static MessageQueryBuilder<static>|Message whereResponseTimeMs($value)
 * @method static MessageQueryBuilder<static>|Message whereRole($value)
 * @method static MessageQueryBuilder<static>|Message whereSentiment($value)
 * @method static MessageQueryBuilder<static>|Message whereSource($value)
 * @method static MessageQueryBuilder<static>|Message whereTone($value)
 * @method static MessageQueryBuilder<static>|Message whereUpdatedAt($value)
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
    'read_at',
    'created_at',
    'updated_at',
])]
#[Table(name: 'chat_messages')]
class Message extends Model
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
            'is_response_from_qa' => 'boolean',
            'is_usage_counted' => 'boolean',
            'message_credits' => 'integer',
            'response_time_ms' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost_in_micro_cents' => 'integer',
        ];
    }

    /**
     * Cascade attachment deletes (and their S3 objects) when the message
     * is deleted via Eloquent — mirrors {@see Conversation::booted}.
     */
    protected static function booted(): void
    {
        static::deleting(static function (Message $message): void {
            $message->attachments()->each(static fn (Attachment $a) => $a->delete());
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    /**
     * Files uploaded with this message. Cascaded above on delete so the
     * S3 object goes with the row.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'chat_message_id');
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

    public function newEloquentBuilder($query): MessageQueryBuilder
    {
        return new MessageQueryBuilder($query);
    }
}
