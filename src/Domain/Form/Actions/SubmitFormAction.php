<?php

namespace Domain\Form\Actions;

use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\DB;

/**
 * Persist one inline-form submission. One transaction:
 *   1. atomically claim the form (lockForUpdate, pending → submitted, once),
 *   2. store the answers on the submission (encrypted at rest),
 *   3. flip the owning message's artifact to submitted for reload state,
 *   4. fire {@see FormSubmitted} (→ CRM webhook).
 *
 * The unguessable form_instance_id (the submission's external_id) + the claim
 * are the idempotency guard; the binding (agent + conversation) is verified by
 * the calling controller before this runs.
 */
class SubmitFormAction
{
    /**
     * @param  array<string, mixed>  $values  Validated field values (restricted to known keys).
     * @return array{status: string, success_message: string, submitted_values: array<string, mixed>}
     */
    public function execute(
        FormSubmission $submission,
        Conversation $conversation,
        array $values,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $requestId = null,
        ?string $assistantMessageExternalId = null,
    ): array {
        $definition = $submission->definition;

        return DB::transaction(function () use ($submission, $conversation, $values, $ip, $userAgent, $requestId, $assistantMessageExternalId, $definition): array {
            /** @var FormSubmission|null $claimed */
            $claimed = FormSubmission::query()->whereKey($submission->id)->lockForUpdate()->first();

            if ($claimed === null || $claimed->status !== FormSubmission::STATUS_PENDING) {
                throw FormNotClaimableException::alreadySubmitted();
            }
            if ($claimed->isExpired()) {
                $claimed->forceFill(['status' => FormSubmission::STATUS_EXPIRED])->save();
                throw FormNotClaimableException::expired();
            }

            // Keep only fields the definition declares (drop anything extra).
            $knownKeys = array_column($definition->schema, 'key');
            $clean = array_intersect_key($values, array_flip($knownKeys));
            $checksum = FormSubmission::checksum($clean);

            $message = $assistantMessageExternalId !== null
                ? Message::query()->where('external_id', $assistantMessageExternalId)->first()
                : null;

            // If this exact answer set is already stored for the form (e.g. the
            // visitor submitted a second render of the same form), don't keep a
            // second copy: drop this instance but still show them the submitted
            // state and a success message — their details are already captured.
            if ($checksum !== null
                && FormSubmission::query()->duplicateOf($definition->id, $checksum)->whereKeyNot($claimed->id)->exists()) {
                $claimed->delete();
                $this->writeSubmittedStateToMessage($message, $submission->external_id);

                return [
                    'status' => FormSubmission::STATUS_SUBMITTED,
                    'success_message' => $definition->success_message ?: 'Thanks — your details have been received.',
                    'submitted_values' => $clean,
                ];
            }

            $claimed->forceFill([
                'status' => FormSubmission::STATUS_SUBMITTED,
                'values' => $clean === [] ? null : $clean,
                'values_checksum' => $checksum,
                'request_id' => $requestId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'chat_message_id' => $message?->id,
                'submitted_at' => now(),
            ])->save();

            $this->writeSubmittedStateToMessage($message, $submission->external_id);

            $agent = $claimed->agent;
            FormSubmitted::dispatch(
                $agent->id,
                $agent->user_id,
                $claimed->id,
                $definition->slug,
                $definition->form_type->value,
                $conversation->external_id,
                true,
                'form',
                $clean,
            );

            return [
                'status' => FormSubmission::STATUS_SUBMITTED,
                'success_message' => $definition->success_message ?: 'Thanks — your details have been received.',
                'submitted_values' => $clean,
            ];
        });
    }

    /**
     * Flip the form artifact's status on the owning assistant message so a reload
     * renders it read-only. Bound by the artifact — we only write into a message
     * that actually carries this instance. PII values are NOT copied here (they
     * stay only in the encrypted submission).
     */
    private function writeSubmittedStateToMessage(?Message $message, string $formInstanceId): void
    {
        if ($message === null) {
            return;
        }

        $metadata = $message->metadata ?? [];
        $artifacts = $metadata['artifacts'] ?? [];

        $found = false;
        foreach ($artifacts as $i => $artifact) {
            if (($artifact['kind'] ?? null) === 'form'
                && ($artifact['data']['form_instance_id'] ?? null) === $formInstanceId) {
                $artifacts[$i]['data']['status'] = FormSubmission::STATUS_SUBMITTED;
                $found = true;
                break;
            }
        }

        if (! $found) {
            return;
        }

        $metadata['artifacts'] = $artifacts;
        $message->metadata = $metadata;
        $message->save();
    }
}
