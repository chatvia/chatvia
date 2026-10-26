<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Domain\Conversation\Models\Conversation;
use Domain\Form\Models\FormSubmission;

/**
 * One booking attempt from the chat: the pending booking instance, its
 * conversation (already checked by the caller), what the visitor sent, and
 * the request details stored on the lead.
 */
final readonly class BookMeetingData
{
    public function __construct(
        public FormSubmission $submission,
        public Conversation $conversation,
        public BookingRequestData $request,
        public ?string $assistantMessageExternalId,
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
