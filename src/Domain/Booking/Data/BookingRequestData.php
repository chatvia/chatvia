<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Carbon\CarbonImmutable;

/**
 * What a visitor sends to book a meeting: the chosen start time and who they
 * are. The time zone is the visitor's own, for the invitation they receive.
 */
final readonly class BookingRequestData
{
    public function __construct(
        public CarbonImmutable $startTime,
        public string $name,
        public string $email,
        public string $timezone,
        public ?string $notes,
    ) {}
}
