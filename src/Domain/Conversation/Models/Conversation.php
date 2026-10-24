<?php

namespace Domain\Conversation\Models;

use App\Identity\Workspace\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\ConversationFactory;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Concerns\CascadesAttachmentDeletes;
use Domain\Conversation\QueryBuilders\ConversationQueryBuilder;
use Domain\Conversation\Services\ConversationRetention;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Topic\Models\ConversationTopic;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Form\Models\FormSubmission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Support\Concerns\HasExternalId;
use Support\Localization\Language\Models\Language;

/**
 * One conversation between an agent and a visitor (or chatter), on the
 * surface it started on: the database copy behind the chat log, analytics and
 * handoff. The live history the chat streams from is the chat memory; this row
 * holds the retention horizon (`expires_at`), who answers right now
 * (`handoff_status`), and whether the visitor cleared it from their view.
 * Deleting it also deletes its uploaded files and its live memory.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property int $chat_session_id
 * @property int $agent_id
 * @property int|null $language_id
 * @property ConversationSource $source
 * @property bool $is_visible
 * @property string|null $title
 * @property Sentiment|null $sentiment
 * @property Tone|null $tone
 * @property Carbon|null $analyzed_at
 * @property int $analysis_attempts end-of-life analysis runs that reached the model (see QueuePendingConversationAnalysisAction)
 * @property int|null $handoff_user_id
 * @property HandoffStatus|null $handoff_status null only on a freshly created model before refresh (the column defaults to `ai`)
 * @property Carbon|null $handoff_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $cleared_at
 * @property string|null $deleted_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent|null $agent
 * @property-read User|null $handoffUser
 * @property-read Language|null $language
 * @property-read FormSubmission|null $lead
 * @property-read Collection<int, Message> $messages
 * @property-read int|null $messages_count
 * @property-read Message|null $lastMessage
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
 * @method static ConversationQueryBuilder<static>|Conversation reachableByWidgetVisitor(int $agentId, ?int $chatterId = null)
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
    'workspace_id',
    'chat_session_id',
    'agent_id',
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
    'cleared_at',
])]
#[Table(name: 'chat_conversations')]
#[RouteKey('external_id')]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use CascadesAttachmentDeletes, HasExternalId, HasFactory, SoftDeletes;

    /** Placeholder title every conversation is created with until it's named. */
    public const string DEFAULT_TITLE = 'New Chat';

    /** `deleted_by` value for a conversation the visitor cleared from their widget. */
    public const string DELETED_BY_VISITOR = 'user';

    /** `deleted_by` value for an admin delete with no known acting user. */
    public const string DELETED_BY_ADMIN = 'admin';

    /**
     * Days a hidden conversation (started, never used) is kept. The widget
     * starts one on every visit and most never get a message, so they go with
     * the next daily prune instead of living a full retention window.
     */
    public const int HIDDEN_LIFETIME_DAYS = 1;

    /**
     * The changes that make a hidden conversation visible: it joins the
     * visitor's list and the chat log, and is kept for the install's full
     * retention window instead of the short life of an unused one.
     *
     * @return array{is_visible: true, expires_at: CarbonInterface}
     */
    public static function visibleAttributes(): array
    {
        return [
            'is_visible' => true,
            'expires_at' => now()->addDays(ConversationRetention::days()),
        ];
    }

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
            'title' => 'encrypted',
            'source' => ConversationSource::class,
            'sentiment' => Sentiment::class,
            'tone' => Tone::class,
            'handoff_status' => HandoffStatus::class,
            'handoff_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'analyzed_at' => 'datetime',
            'cleared_at' => 'datetime',
            'is_visible' => 'boolean',
        ];
    }

    /** @return ConversationQueryBuilder<self> */
    public function newEloquentBuilder($query): ConversationQueryBuilder
    {
        return new ConversationQueryBuilder($query);
    }

    /**
     * Delete attached files when the conversation is deleted. The shared
     * {@see CascadesAttachmentDeletes::cascadeAttachmentDeletes()} body routes
     * the attachment deletes through Eloquent so each fires its `deleting`
     * observer and wipes the backing S3 object (the DB-level cascade on
     * `chat_attachments.chat_conversation_id` would otherwise leak them).
     *
     * Any model owning attachments MUST repeat this hook (see
     * {@see Message::booted}): the registration stays explicit per model on
     * purpose — only the shared body lives in the trait, not the hook itself.
     *
     * Runs on both soft- and force-delete: a soft-deleted conversation is
     * still "deleted" from the user's perspective, and they expect their
     * uploaded files to go with it. Re-attaching a file after un-delete
     * isn't a supported flow.
     */
    protected static function booted(): void
    {
        static::deleting(static function (Conversation $conversation): void {
            $conversation->cascadeAttachmentDeletes();

            // Flush every Redis entry tagged with this conversation
            // (messages, sources, ...) so the cache matches the DB on
            // every delete path: DeleteConversationAction,
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
     * The latest message in this conversation, by id (insertion order). A
     * dedicated HasOne (latestOfMany) so eager-loading returns one row PER
     * parent: a `messages()->latest()->limit(1)` inside `with()` applies the
     * LIMIT to the single batched query, yielding one row across ALL parents.
     *
     * @return HasOne<Message, $this>
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'chat_conversation_id')->latestOfMany();
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
     * Committed topics this conversation belongs to (multi-label). Suggestion
     * rows in the pivot (topic_id NULL) don't join here.
     *
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'conversation_topics', 'chat_conversation_id', 'topic_id');
    }

    /**
     * Emergent topic suggestions for this conversation (topic_id NULL,
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
     * The most recent submission captured in this conversation. Kept named
     * `lead` for the chat-log / handoff readers that display the captured
     * contact's name/email via FormSubmission's field accessors.
     *
     * @return HasOne<FormSubmission, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(FormSubmission::class, 'chat_conversation_id')->latestOfMany();
    }

    /**
     * Denormalized from the session at create — a conversation never changes agent.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The admin user who has taken over this conversation (handoff).
     *
     * @return BelongsTo<User, $this>
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
        return $this->handoff_status === HandoffStatus::Human;
    }

    /**
     * Whether this conversation happens in the Chat Widget — the only surface
     * with live chat, so the only one a handoff can bring a person into.
     */
    public function isSourceWidget(): bool
    {
        return $this->source === ConversationSource::Widget;
    }

    public function displayName(): ?string
    {
        if ($this->lead?->name) {
            return $this->lead->name;
        }

        return $this->session?->chatter?->name ?? $this->session?->user?->name;
    }

    public function displayEmail(): ?string
    {
        if ($this->lead?->email) {
            return $this->lead->email;
        }

        return $this->session?->chatter?->email ?? $this->session?->user?->email;
    }

    public function canBeTakenOverBy(User $admin): bool
    {
        // Live takeover streams staff replies into the embedded widget only.
        // The hosted chat, the bar, the API and the messaging channels have no
        // relay — their bot keeps answering — so a takeover there would leave
        // two writers in one conversation. Mirrors the dashboard's gate.
        if ($this->source !== ConversationSource::Widget) {
            return false;
        }

        if ($this->isHandedOff() && $this->handoff_user_id !== $admin->id) {
            return false;
        }

        $agent = $this->session?->agent;

        if (! $agent) {
            return false;
        }

        return Gate::forUser($admin)->allows('access-agent', $agent);
    }

    /**
     * True while this operator holds the conversation: it is with a human and
     * that human is them. Only the holder may reply, type or hand it back.
     */
    public function isHeldBy(User $admin): bool
    {
        return $this->isHandedOff() && $this->handoff_user_id === $admin->id;
    }

    /**
     * Whether this conversation belongs to the given agent. The dashboard's
     * handoff routes bind the agent and the conversation separately, so they
     * check the pair with this before acting.
     */
    public function belongsToAgent(Agent $agent): bool
    {
        return $this->agent_id === $agent->id;
    }

    /**
     * Activity-based retention extension. When the conversation is close
     * to its current horizon (default: within 3 days), push expires_at
     * out by another full retention window. The threshold avoids touching
     * the row on every single message while still preventing an actively-
     * used conversation from getting swept by the daily prune.
     *
     * Returns true when the row was updated, false when the current
     * horizon is still far enough out to leave alone. A hidden conversation
     * gets its full window when its first message makes it visible
     * ({@see visibleAttributes()}), before this runs for that message.
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
