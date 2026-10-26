<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Carbon\CarbonImmutable;

/**
 * A meeting the scheduler confirmed: when it starts and the scheduler's own
 * references to the event and the invitee.
 */
final readonly class BookedMeetingData
{
    public function __construct(
        public CarbonImmutable $startTime,
        public ?string $eventUri,
        public ?string $inviteeUri,
    ) {}
}
