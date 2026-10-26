<?php

declare(strict_types=1);

namespace Domain\Booking\Enums;

/**
 * Why a meeting could not be shown or booked. The HTTP layer picks the
 * response status from it, and the chat block its message.
 */
enum BookingFailure: string
{
    /** The workspace has connected no scheduler (Calendly or Cal.com), or the connection broke. */
    case NotConnected = 'not_connected';

    /** The scheduler refuses booking through its API (a free Calendly plan); the visitor books on its booking page instead. */
    case BookingNotAllowed = 'booking_not_allowed';

    /** The time was taken between showing it and booking it. */
    case SlotTaken = 'slot_taken';

    /** The scheduler could not be reached or returned an error. */
    case Unavailable = 'unavailable';

    public function message(): string
    {
        return match ($this) {
            self::NotConnected => 'Booking is not set up for this agent.',
            self::BookingNotAllowed => 'This meeting can only be booked on the booking page.',
            self::SlotTaken => 'That time was just taken. Please pick another one.',
            self::Unavailable => 'The calendar could not be reached. Please try again in a moment.',
        };
    }
}
