<?php

namespace Domain\Form\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A form submission was captured — from an inline form OR the conversational
 * capture path. Fully denormalized with decrypted answers so the queued CRM
 * webhook listener never re-reads the (encrypted) submission row.
 */
final class FormSubmitted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $values  Decrypted submission answers keyed by field.
     */
    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly int $submissionId,
        public readonly string $formSlug,
        public readonly string $formType,
        public readonly ?string $conversationExternalId,
        public readonly bool $created,
        public readonly string $source,
        public readonly array $values,
    ) {}
}
