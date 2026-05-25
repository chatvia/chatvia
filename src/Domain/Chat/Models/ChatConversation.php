<?php

namespace Domain\Chat\Models;

use App\Auth\Models\User;
use App\Language\Models\Language;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Lead;
use Domain\Chat\QueryBuilders\ChatConversationQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Support\Traits\SupportsExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int $chat_session_id
 * @property int|null $language_id
 * @property int|null $chat_topic_id
 * @property bool $visible
 * @property string|null $title
 * @property string|null $sentiment
 * @property string|null $tone
 * @property int|null $handoff_user_id
 * @property string $handoff_status
 * @property Carbon|null $handoff_at
 * @property Carbon|null $last_activity_at
 * @property string|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read User|null $handoffUser
 * @property-read Language|null $language
 * @property-read Lead|null $lead
 * @property-read Collection<int, ChatMessage> $messages
 * @property-read int|null $messages_count
 * @property-read ChatSession $session
 * @property-read ChatTopic|null $topic
 *
 * @method static \Database\Factories\ChatConversationFactory factory($count = null, $state = [])
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByConfidenceScore(?string $confidenceScore)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByCountry(?string $countryId)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByDateRange(?string $startDate, ?string $endDate)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByFeedback(?string $feedback)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByOnlineStatus(?bool $onlineOnly)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterBySentiment(?string $sentiment)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterBySource(?string $source)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByTone(?string $tone)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation filterByTopic(?string $topicId)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation forAgent(string $agentId)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation newModelQuery()
 * @method static ChatConversationQueryBuilder<static>|ChatConversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation onlyTrashed()
 * @method static ChatConversationQueryBuilder<static>|ChatConversation onlyVisible()
 * @method static ChatConversationQueryBuilder<static>|ChatConversation orderByLatestActivity()
 * @method static ChatConversationQueryBuilder<static>|ChatConversation query()
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereChatSessionId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereChatTopicId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereCreatedAt($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereDeletedAt($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereDeletedBy($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereExternalId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereHandoffAt($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereHandoffStatus($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereHandoffUserId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereLanguageId($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereLastActivityAt($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereSentiment($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereTitle($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereTone($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereUpdatedAt($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation whereVisible($value)
 * @method static ChatConversationQueryBuilder<static>|ChatConversation withSessionAndTopic()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChatConversation withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'external_id',
    'chat_session_id',
    'language_id',
    'chat_topic_id',
    'visible',
    'title',
    'sentiment',
    'tone',
    'handoff_user_id',
    'handoff_status',
    'handoff_at',
    'last_activity_at',
    'deleted_by',
])]
class ChatConversation extends Model
{
    use HasFactory, SoftDeletes, SupportsExternalId;

    protected array $hmacEncryptionColumns = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'handoff_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function newEloquentBuilder($query): ChatConversationQueryBuilder
    {
        return new ChatConversationQueryBuilder($query);
    }

    /**
     * @return BelongsTo<ChatSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<ChatTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ChatTopic::class, 'chat_topic_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * @return HasOne<Lead, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'chat_conversation_id');
    }

    /**
     * @return HasOneThrough<Agent, ChatSession, $this>
     */
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
