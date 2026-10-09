<?php

declare(strict_types=1);

namespace Domain\Form\Events;

use Domain\Form\Data\CapturedSubmissionData;
use Domain\Form\Enums\FormType;
use Domain\Form\Listeners\PushSubmissionToCrm;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A form submission was captured — from an inline form OR the conversational
 * capture path. The submission travels as {@see CapturedSubmissionData}, so
 * the queued CRM webhook listener ({@see PushSubmissionToCrm}) never has to
 * re-read the row.
 *
 * Queued listeners store this event serialized. {@see __serialize()} keeps
 * the flat field list the event had before it carried a DTO (`agentId`,
 * `workspaceId`, … `values`), so a payload queued by either version is read
 * by the other: a deploy never strands a waiting CRM delivery.
 */
final readonly class FormSubmitted
{
    use Dispatchable;

    public function __construct(
        public CapturedSubmissionData $submission,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $submission = $this->submission;

        return [
            'agentId' => $submission->agentId,
            'workspaceId' => $submission->workspaceId,
            'submissionId' => $submission->submissionId,
            'formSlug' => $submission->formSlug,
            'formType' => $submission->formType->value,
            'conversationExternalId' => $submission->conversationExternalId,
            'created' => $submission->created,
            'source' => $submission->source,
            'values' => $submission->values,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  the flat field list written by {@see __serialize()}
     */
    public function __unserialize(array $data): void
    {
        $this->submission = new CapturedSubmissionData(
            agentId: (int) $data['agentId'],
            workspaceId: (int) $data['workspaceId'],
            submissionId: (int) $data['submissionId'],
            formSlug: (string) $data['formSlug'],
            formType: FormType::from((string) $data['formType']),
            conversationExternalId: isset($data['conversationExternalId']) ? (string) $data['conversationExternalId'] : null,
            created: (bool) $data['created'],
            source: (string) $data['source'],
            values: (array) $data['values'],
        );
    }
}
