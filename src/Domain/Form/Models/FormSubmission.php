<?php

namespace Domain\Form\Models;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * A form submission — the single store for every captured contact. Created
 * `pending` when an agent renders a form (the unique external_id is the
 * idempotency / form_instance_id key), or directly `submitted` for the
 * conversational capture path. `values` holds the answers as plaintext JSON.
 * The named accessors (name/email/…) read `values` so lead-shaped readers keep
 * working.
 *
 * @property int $id
 * @property string $external_id
 * @property int $form_definition_id
 * @property int $agent_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property string $status
 * @property array<string, mixed>|null $values
 * @property string|null $values_checksum
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $expires_at
 * @property Carbon|null $submitted_at
 * @property-read string|null $name
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $job_title
 * @property-read string|null $company
 * @property-read string|null $location
 * @property-read string|null $message
 *
 * @method static \Database\Factories\FormSubmissionFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class FormSubmission extends Model
{
    use HasExternalId, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    /**
     * Fingerprint of an answer set, for de-duplication. This HMAC over the
     * canonical (recursively key-sorted) JSON is stable for identical answers
     * and unguessable without the app key (it digests PII, like the retired
     * `leads.email_hash`), so the de-dupe index matches without exposing the
     * answers. Returns null for an empty answer set (pending rows, blank
     * captures) — those never de-dupe.
     *
     * @param  array<string, mixed>|null  $values
     */
    public static function checksum(?array $values): ?string
    {
        if (empty($values)) {
            return null;
        }

        return hash_hmac('sha256', (string) json_encode(self::canonicalize($values)), (string) config('app.key'));
    }

    /**
     * Recursively key-sort so the fingerprint is independent of answer order.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function canonicalize(array $values): array
    {
        ksort($values);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::canonicalize($value);
            }
        }

        return $values;
    }

    /**
     * Of the given form ids, the ones this visitor SESSION has already submitted —
     * so an agent never re-renders a form the visitor already completed. Scoped by
     * session (the visitor's identity across conversations), not by conversation,
     * so a booking made in an earlier chat in the same session still counts.
     *
     * @param  array<int, int>  $formDefinitionIds
     * @return array<int, int>
     */
    public static function submittedFormIdsForSession(int $sessionId, array $formDefinitionIds): array
    {
        if ($formDefinitionIds === []) {
            return [];
        }

        return self::query()
            ->where('status', self::STATUS_SUBMITTED)
            ->whereIn('form_definition_id', $formDefinitionIds)
            ->whereHas('conversation', fn (Builder $query): Builder => $query->where('chat_session_id', $sessionId))
            ->distinct()
            ->pluck('form_definition_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Already-submitted rows carrying the same answer fingerprint for a form —
     * the de-dupe probe (the `form_definition_id, values_checksum` index serves it).
     */
    #[Scope]
    protected function duplicateOf(Builder $query, int $formDefinitionId, string $checksum): void
    {
        $query->where('form_definition_id', $formDefinitionId)
            ->where('status', self::STATUS_SUBMITTED)
            ->where('values_checksum', $checksum);
    }

    /**
     * Still open for a first submit: pending and not past its TTL.
     */
    public function isClaimable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * One answer by field key, as a string (null when absent / non-scalar).
     */
    public function field(string $key): ?string
    {
        $value = ($this->values ?? [])[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function getNameAttribute(): ?string
    {
        return $this->field('name');
    }

    public function getEmailAttribute(): ?string
    {
        return $this->field('email');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->field('phone');
    }

    public function getJobTitleAttribute(): ?string
    {
        return $this->field('job_title');
    }

    public function getCompanyAttribute(): ?string
    {
        return $this->field('company');
    }

    public function getLocationAttribute(): ?string
    {
        return $this->field('location');
    }

    public function getMessageAttribute(): ?string
    {
        return $this->field('message');
    }

    /**
     * @return BelongsTo<FormDefinition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'chat_message_id');
    }
}
