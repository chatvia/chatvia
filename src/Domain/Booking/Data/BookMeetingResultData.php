<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Carbon\CarbonImmutable;
use Domain\Form\Enums\FormSubmissionStatus;

/**
 * A booked meeting as the chat block shows it: the booked state, the start
 * time and the confirmation text.
 */
final readonly class BookMeetingResultData
{
    public function __construct(
        public FormSubmissionStatus $status,
        public CarbonImmutable $startTime,
        public string $message,
    ) {}
}
