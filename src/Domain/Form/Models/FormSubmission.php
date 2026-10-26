<?php

namespace Domain\Form\Models;

use Database\Factories\FormSubmissionFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Exceptions\FormNotClaimableException;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
 * @property int $workspace_id
 * @property int $agent_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property FormSubmissionStatus $status
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormSubmission submitted()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormSubmission abandoned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FormSubmission duplicateOf(int $formDefinitionId, string $checksum)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[RouteKey('external_id')]
class FormSubmission extends Model
{
    /** @use HasFactory<FormSubmissionFactory> */
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'status' => FormSubmissionStatus::class,
            'values' => 'array',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
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
            ->where('status', FormSubmissionStatus::Submitted)
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
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function duplicateOf(Builder $query, int $formDefinitionId, string $checksum): void
    {
        $query->where('form_definition_id', $formDefinitionId)
            ->where('status', FormSubmissionStatus::Submitted)
            ->where('values_checksum', $checksum);
    }

    /**
     * Captured contacts — the rows the Forms pages, exports and analytics show.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function submitted(Builder $query): void
    {
        $query->where('status', FormSubmissionStatus::Submitted);
    }

    /**
     * Pending forms whose time to be sent has passed. They can never be claimed
     * and carry no answers.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function abandoned(Builder $query): void
    {
        $query->where('status', FormSubmissionStatus::Pending)
            ->where('expires_at', '<', now());
    }

    public function isPending(): bool
    {
        return $this->status === FormSubmissionStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Lock this pending row so two submits of the same instance can't both
     * win; an expired one is marked so and rejected. Call inside a transaction.
     *
     * @throws FormNotClaimableException
     */
    public function claimPending(): self
    {
        /** @var self|null $claimed */
        $claimed = self::query()->whereKey($this->id)->lockForUpdate()->first();

        if ($claimed === null || ! $claimed->isPending()) {
            throw FormNotClaimableException::alreadySubmitted();
        }

        if ($claimed->isExpired()) {
            $claimed->forceFill(['status' => FormSubmissionStatus::Expired])->save();

            throw FormNotClaimableException::expired();
        }

        return $claimed;
    }

    /**
     * One answer by field key, as a string (null when absent / non-scalar).
     */
    public function field(string $key): ?string
    {
        $value = ($this->values ?? [])[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('name'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('email'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function phone(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('phone'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function jobTitle(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('job_title'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function company(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('company'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function location(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('location'));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function message(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->field('message'));
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
     * The chat message the submission was captured from. Named after its key —
     * `message` is the lead's message field ({@see self::message()} above).
     *
     * @return BelongsTo<Message, $this>
     */
    public function chatMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'chat_message_id');
    }
}
