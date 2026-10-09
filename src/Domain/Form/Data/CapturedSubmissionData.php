<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Domain\Form\Enums\FormType;
use Domain\Form\Events\FormSubmitted;

/**
 * A captured form submission as the rest of the system hears about it
 * ({@see FormSubmitted}): which agent, workspace, form and conversation, plus
 * the answers. Fully denormalized, so a queued listener never has to re-read
 * the submission row.
 */
final readonly class CapturedSubmissionData
{
    /**
     * @param  bool  $created  true when the submission created a new lead (always so for an inline form)
     * @param  string  $source  where it was captured (`form` for an inline form)
     * @param  array<string, mixed>  $values  the answers, keyed by field
     */
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public int $submissionId,
        public string $formSlug,
        public FormType $formType,
        public ?string $conversationExternalId,
        public bool $created,
        public string $source,
        public array $values,
    ) {}
}
