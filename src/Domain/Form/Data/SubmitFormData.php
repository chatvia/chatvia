<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Domain\Conversation\Models\Conversation;
use Domain\Form\Models\FormSubmission;

/**
 * One visitor's answers to an inline form, with the request details kept on
 * the captured submission. The HTTP layer has already checked the form
 * instance belongs to this agent and conversation.
 */
final readonly class SubmitFormData
{
    /**
     * @param  array<string, mixed>  $values  The submitted field values (keys not in the form are dropped).
     * @param  string|null  $assistantMessageExternalId  The assistant message that shows the form, so its artifact can show "submitted" on reload.
     */
    public function __construct(
        public FormSubmission $submission,
        public Conversation $conversation,
        public array $values,
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $requestId = null,
        public ?string $assistantMessageExternalId = null,
    ) {}
}
