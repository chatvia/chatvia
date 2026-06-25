<?php

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Data\CaptureLeadData;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Models\FormSubmission;
use Domain\Form\Services\DefaultLeadForm;

/**
 * Record a contact captured conversationally (the `capture-lead` tool) as a
 * submission under the agent's default lead form — so it shows up in Forms →
 * Submissions alongside inline-form submissions, and fires the same CRM webhook.
 */
class RecordConversationalSubmissionAction
{
    public function __construct(
        private readonly DefaultLeadForm $defaultLeadForm,
    ) {}

    public function execute(Agent $agent, ?Conversation $conversation, CaptureLeadData $data): FormSubmission
    {
        $definition = $this->defaultLeadForm->forAgent($agent);
        $values = $data->values();
        $checksum = FormSubmission::checksum($values);

        // The capture-lead tool fires whenever the agent re-confirms a contact, so
        // the same details arrive repeatedly. If this answer set is already stored
        // for this form, return the existing row — no duplicate, no second CRM push.
        if ($checksum !== null) {
            $existing = FormSubmission::query()->duplicateOf($definition->id, $checksum)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $submission = FormSubmission::create([
            'form_definition_id' => $definition->id,
            'agent_id' => $agent->id,
            'chat_conversation_id' => $conversation?->id,
            'status' => FormSubmission::STATUS_SUBMITTED,
            'values' => $values === [] ? null : $values,
            'values_checksum' => $checksum,
            'ip_address' => $data->ip,
            'user_agent' => $data->userAgent,
            'submitted_at' => now(),
        ]);

        FormSubmitted::dispatch(
            $agent->id,
            $agent->user_id,
            $submission->id,
            $definition->slug,
            $definition->form_type->value,
            $conversation?->external_id,
            true,
            $data->source,
            $values,
        );

        return $submission;
    }
}
