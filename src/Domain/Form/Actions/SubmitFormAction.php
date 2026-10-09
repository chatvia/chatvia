<?php

namespace Domain\Form\Actions;

use Domain\Conversation\Models\Message;
use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Data\FormSubmitResultData;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormDefinition;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Persist one inline-form submission, in one transaction:
 *   1. atomically claim the form (lockForUpdate, pending → submitted, once),
 *   2. store the answers on the submission (plaintext JSON in `values`),
 *   3. flip the owning message's artifact to submitted for reload state,
 *   4. fire {@see FormSubmitted} (→ CRM webhook).
 *
 * The unguessable form_instance_id (the submission's external_id) and the
 * claim are the idempotency guard; the binding (agent + conversation) is
 * checked by the calling controller before this runs.
 */
class SubmitFormAction
{
    private const string DEFAULT_SUCCESS_MESSAGE = 'Thanks — your details have been received.';

    /** The capture path, as the CRM payload names it. */
    private const string SOURCE = 'form';

    public function execute(SubmitFormData $data): FormSubmitResultData
    {
        $definition = $data->submission->definition;

        return DB::transaction(function () use ($data, $definition): FormSubmitResultData {
            $claimed = $this->claim($data->submission);

            // Keep only the fields the form declares; drop anything extra.
            $values = array_intersect_key($data->values, array_flip(array_column($definition->schema, 'key')));
            $checksum = FormSubmission::checksum($values);

            $message = $data->assistantMessageExternalId !== null
                ? Message::query()->where('external_id', $data->assistantMessageExternalId)->first()
                : null;

            // The visitor may send the same answers again (e.g. a second render
            // of the same form): keep one copy, but still show them the
            // submitted state — their details are already captured.
            if ($this->isDuplicate($claimed, $definition, $checksum)) {
                $claimed->delete();
                $this->markArtifactSubmitted($message, $data->submission->external_id);
            } else {
                $this->store($claimed, $values, $checksum, $message, $data);
                $this->markArtifactSubmitted($message, $data->submission->external_id);
                $this->announce($claimed, $definition, $data, $values);
            }

            return new FormSubmitResultData(
                status: FormSubmissionStatus::Submitted,
                success_message: $definition->success_message ?: self::DEFAULT_SUCCESS_MESSAGE,
                submitted_values: $values,
            );
        });
    }

    /**
     * Lock the pending row so two submits of the same form can't both win;
     * an expired form is marked so and rejected.
     */
    private function claim(FormSubmission $submission): FormSubmission
    {
        /** @var FormSubmission|null $claimed */
        $claimed = FormSubmission::query()->whereKey($submission->id)->lockForUpdate()->first();

        if ($claimed === null || ! $claimed->isPending()) {
            throw FormNotClaimableException::alreadySubmitted();
        }

        if ($claimed->isExpired()) {
            $claimed->forceFill(['status' => FormSubmissionStatus::Expired])->save();

            throw FormNotClaimableException::expired();
        }

        return $claimed;
    }

    private function isDuplicate(FormSubmission $claimed, FormDefinition $definition, ?string $checksum): bool
    {
        return $checksum !== null
            && FormSubmission::query()->duplicateOf($definition->id, $checksum)->whereKeyNot($claimed->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function store(FormSubmission $claimed, array $values, ?string $checksum, ?Message $message, SubmitFormData $data): void
    {
        $claimed->forceFill([
            'status' => FormSubmissionStatus::Submitted,
            'values' => $values === [] ? null : $values,
            'values_checksum' => $checksum,
            'request_id' => $data->requestId,
            'ip_address' => $data->ip,
            'user_agent' => $data->userAgent,
            'chat_message_id' => $message?->id,
            'submitted_at' => now(),
        ])->save();
    }

    /**
     * Tell the rest of the system a contact was captured (the CRM webhook
     * listens). The event carries the answers, so its listener never re-reads
     * the row.
     *
     * @param  array<string, mixed>  $values
     */
    private function announce(FormSubmission $claimed, FormDefinition $definition, SubmitFormData $data, array $values): void
    {
        $agent = $claimed->agent;

        FormSubmitted::dispatch(new CapturedSubmissionData(
            agentId: $agent->id,
            workspaceId: $agent->user_id,
            submissionId: $claimed->id,
            formSlug: $definition->slug,
            formType: $definition->form_type,
            conversationExternalId: $data->conversation->external_id,
            created: true,
            source: self::SOURCE,
            values: $values,
        ));
    }

    /**
     * Flip the form artifact's status on the owning assistant message so a
     * reload renders it read-only. Only a message that actually carries this
     * form instance is written. The answers (often personal data) are NOT
     * copied there: they live only on the submission row, so deleting the
     * submission removes them everywhere.
     */
    private function markArtifactSubmitted(?Message $message, string $formInstanceId): void
    {
        if (! $message instanceof Message) {
            return;
        }

        $metadata = $message->metadata ?? [];
        $artifacts = $metadata['artifacts'] ?? [];

        $index = array_find_key($artifacts, fn (mixed $artifact): bool => ($artifact['kind'] ?? null) === 'form'
            && ($artifact['data']['form_instance_id'] ?? null) === $formInstanceId);

        if ($index === null) {
            return;
        }

        $artifacts[$index]['data']['status'] = FormSubmissionStatus::Submitted->value;
        $metadata['artifacts'] = $artifacts;
        $message->metadata = $metadata;
        $message->save();
    }
}
