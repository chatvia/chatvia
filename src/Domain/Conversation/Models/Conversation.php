<?php

namespace Domain\Conversation\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Lead\Models\Lead;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Support\Localization\Language\Models\Language;
use Support\Traits\SupportsExternalId;

/**
 * @property int $id
 * @property string $external_id
 * @property int $chat_session_id
 * @property int|null $language_id
 * @property ConversationSource $source
 * @property bool $is_visible
 * @property string|null $title
 * @property string|null $sentiment
 * @property string|null $tone
 * @property Carbon|null $analyzed_at
 * @property int|null $handoff_user_id
 * @property string $handoff_status
 * @property Carbon|null $handoff_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $expires_at
 * @property string|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read User|null $handoffUser
 * @property-read Language|null $language
 * @property-read Lead|null $lead
 * @property-read Collection<int, Message> $messages
 * @property-read int|null $messages_count
 * @property-read Session $session
 * @property-read Collection<int, Topic> $topics
 * @property-read Collection<int, ConversationTopic> $suggestedTopics
 *
 * @method static \Database\Factories\ConversationFactory factory($count = null, $state = [])
 * @method static ConversationQueryBuilder<static>|Conversation filterByConfidenceScore(?string $confidenceScore)
 * @method static ConversationQueryBuilder<static>|Conversation filterByCountry(?string $countryId)
 * @method static ConversationQueryBuilder<static>|Conversation filterByDateRange(?string $startDate, ?string $endDate)
 * @method static ConversationQueryBuilder<static>|Conversation filterByFeedback(?string $feedback)
 * @method static ConversationQueryBuilder<static>|Conversation filterByOnlineStatus(?bool $onlineOnly)
 * @method static ConversationQueryBuilder<static>|Conversation filterBySentiment(?string $sentiment)
 * @method static ConversationQueryBuilder<static>|Conversation filterBySource(?string $source)
 * @method static ConversationQueryBuilder<static>|Conversation filterByTone(?string $tone)
 * @method static ConversationQueryBuilder<static>|Conversation filterByTopic(?string $topicId)
 * @method static ConversationQueryBuilder<static>|Conversation forAgent(string $agentId)
 * @method static ConversationQueryBuilder<static>|Conversation newModelQuery()
 * @method static ConversationQueryBuilder<static>|Conversation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation onlyTrashed()
 * @method static ConversationQueryBuilder<static>|Conversation onlyVisible()
 * @method static ConversationQueryBuilder<static>|Conversation orderByLatestActivity()
 * @method static ConversationQueryBuilder<static>|Conversation query()
 * @method static ConversationQueryBuilder<static>|Conversation whereSessionId($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereAnalyzedAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereCreatedAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereDeletedAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereDeletedBy($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereExpiresAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereExternalId($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereHandoffAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereHandoffStatus($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereHandoffUserId($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereId($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereLanguageId($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereLastActivityAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereSentiment($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereTitle($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereTone($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereUpdatedAt($value)
 * @method static ConversationQueryBuilder<static>|Conversation whereIsVisible($value)
 * @method static ConversationQueryBuilder<static>|Conversation withSessionAndTopic()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Conversation withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'external_id',
    'chat_session_id',
    'language_id',
    'source',
    'is_visible',
    'title',
    'sentiment',
    'tone',
    'analyzed_at',
    'handoff_user_id',
    'handoff_status',
    'handoff_at',
    'last_activity_at',
    'expires_at',
    'deleted_by',
])]
#[Table(name: 'chat_conversations')]
class Conversation extends Model
{
    use HasFactory, SoftDeletes, SupportsExternalId;

    /** Placeholder title every conversation is created with until it's named. */
    public const string DEFAULT_TITLE = 'New Chat';

    protected array $hmacEncryptionColumns = [
        'id',
    ];

    /**
     * True while the conversation still carries its placeholder title, i.e. it
     * has not been named from its messages yet.
     */
    public function needsTitle(): bool
    {
        return blank($this->title) || $this->title === self::DEFAULT_TITLE;
    }

    protected function casts(): array
    {
        return [
            'source' => ConversationSource::class,
            'handoff_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'analyzed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function newEloquentBuilder($query): ConversationQueryBuilder
    {
        return new ConversationQueryBuilder($query);
    }

    /**
     * Delete attached files when the conversation is deleted. The DB-level
     * cascade on `chat_attachments.chat_conversation_id` removes rows but
     * skips Eloquent events — so the S3 objects would leak. Triggering
     * attachment deletes through Eloquent first fires the `Attachment`
     * `deleting` observer that wipes the underlying file.
     *
     * Runs on both soft- and force-delete: a soft-deleted conversation is
     * still "deleted" from the user's perspective, and they expect their
     * uploaded files to go with it. Re-attaching a file after un-delete
     * isn't a supported flow.
     */
    protected static function booted(): void
    {
        static::deleting(static function (Conversation $conversation): void {
            $conversation->attachments()->each(static fn (Attachment $a) => $a->delete());

            // Flush every Redis entry tagged with this conversation
            // (messages, sources, ...) so the cache matches the DB on
            // every delete path: ConversationManager::deleteConversation,
            // AdminDeleteConversationAction, and the scheduled prune.
            Cache::driver('chat')
                ->tags(ChatCache::conversationTag($conversation->external_id))
                ->flush();
        });
    }

    /**
     * @return BelongsTo<Session, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'chat_session_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'chat_conversation_id');
    }

    /**
     * All files uploaded into this conversation. Created by the upload
     * endpoints and surfaced to the UI as chips.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'chat_conversation_id');
    }

    /**
     * Subset used by the streaming pipeline to decide PDF-Q&A routing.
     *
     * @return HasMany<Attachment, $this>
     */
    public function pdfAttachments(): HasMany
    {
        return $this->attachments()->where('mime_type', 'application/pdf');
    }

    /**
     * Committed topics this conversation belongs to (multi-label). Suggestion
     * rows in the pivot (chat_topic_id NULL) don't join here.
     *
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'conversation_topics', 'chat_conversation_id', 'chat_topic_id');
    }

    /**
     * Emergent topic suggestions for this conversation (chat_topic_id NULL,
     * suggested_label set) — surfaced for promotion, not yet real topics.
     *
     * @return HasMany<ConversationTopic, $this>
     */
    public function suggestedTopics(): HasMany
    {
        return $this->hasMany(ConversationTopic::class, 'chat_conversation_id')->whereNotNull('suggested_label');
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
     * @return HasOneThrough<Agent, Session, $this>
     */
    public function agent(): HasOneThrough
    {
        return $this->hasOneThrough(
            Agent::class,
            Session::class,
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

    public function displayName(): ?string
    {
        if ($this->lead?->name) {
            return $this->lead->name;
        }

        return $this->session?->user?->name;
    }

    public function displayEmail(): ?string
    {
        if ($this->lead?->email) {
            return $this->lead->email;
        }

        return $this->session?->user?->email;
    }

    public function canBeTakenOverBy(User $admin): bool
    {
        if ($this->isHandedOff() && $this->handoff_user_id !== $admin->id) {
            return false;
        }

        $agent = $this->session?->agent;

        if (! $agent) {
            return false;
        }

        return Gate::forUser($admin)->allows('access-agent', $agent);
    }

    public function canBeReturnedBy(User $admin): bool
    {
        return $this->isHandedOff() && $this->handoff_user_id === $admin->id;
    }

    /**
     * Admin-side operability check used by handoff-write endpoints.
     * Bundles the `access-agent` Gate with a session-agent equality guard
     * so the 4 handoff controllers don't each duplicate the same `if`.
     */
    public function isOperableBy(User $admin, Agent $agent): bool
    {
        return Gate::forUser($admin)->allows('access-agent', $agent)
            && $this->session?->agent_id === $agent->id;
    }

    /**
     * Activity-based retention extension. When the conversation is close
     * to its current horizon (default: within 3 days), push expires_at
     * out by another full retention window. The threshold avoids touching
     * the row on every single message while still preventing an actively-
     * used conversation from getting swept by the daily prune.
     *
     * Returns true when the row was updated, false when the current
     * horizon is still far enough out to leave alone.
     */
    public function extendRetentionIfDueSoon(int $newRetentionDays, int $renewalThresholdDays = 3): bool
    {
        if ($this->expires_at !== null && $this->expires_at->gt(now()->addDays($renewalThresholdDays))) {
            return false;
        }

        $this->update(['expires_at' => now()->addDays($newRetentionDays)]);

        return true;
    }
}
