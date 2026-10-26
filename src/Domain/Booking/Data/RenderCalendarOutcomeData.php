<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

/**
 * What came of asking to show a booking calendar. Exactly one of:
 *
 *  - rendered: a fresh booking instance was issued ({@see $calendar});
 *  - already booked: this visitor's session booked a meeting before
 *    ({@see $bookedStartTime}, ISO UTC), so nothing was issued;
 *  - link only: the meeting type needs answers the chat does not ask for, or
 *    the channel cannot draw a calendar, so the visitor books on its page
 *    ({@see $bookingUrl});
 *  - not found: no meeting type matched; {@see $meetingTypeNames} lists them.
 */
final readonly class RenderCalendarOutcomeData
{
    /**
     * @param  list<string>  $meetingTypeNames
     */
    private function __construct(
        public ?CalendarArtifactData $calendar = null,
        public ?string $bookedStartTime = null,
        public bool $alreadyBooked = false,
        public ?string $bookingUrl = null,
        public array $meetingTypeNames = [],
    ) {}

    public static function rendered(CalendarArtifactData $calendar): self
    {
        return new self(calendar: $calendar);
    }

    public static function alreadyBooked(?string $startTime): self
    {
        return new self(bookedStartTime: $startTime, alreadyBooked: true);
    }

    public static function linkOnly(?string $bookingUrl): self
    {
        return new self(bookingUrl: $bookingUrl);
    }

    /**
     * @param  list<string>  $meetingTypeNames
     */
    public static function notFound(array $meetingTypeNames): self
    {
        return new self(meetingTypeNames: $meetingTypeNames);
    }
}
