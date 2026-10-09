<?php

declare(strict_types=1);

namespace Domain\Form\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A form submission was captured — from an inline form OR the conversational
 * capture path. Fully denormalized, answers included, so the queued CRM
 * webhook listener never has to re-read the submission row.
 */
final readonly class FormSubmitted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $values  Submission answers keyed by field.
     */
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public int $submissionId,
        public string $formSlug,
        public string $formType,
        public ?string $conversationExternalId,
        public bool $created,
        public string $source,
        public array $values,
    ) {}
}
