<?php

declare(strict_types=1);

namespace Domain\Booking\Exceptions;

use Domain\Booking\Enums\BookingFailure;
use RuntimeException;
use Throwable;

/**
 * A meeting could not be listed or booked ({@see BookingFailure}). When the
 * visitor can still book on the scheduler's own page, {@see $fallbackUrl}
 * points there.
 */
class BookingException extends RuntimeException
{
    public function __construct(
        public readonly BookingFailure $reason,
        public readonly ?string $fallbackUrl = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($reason->message(), previous: $previous);
    }

    public static function notConnected(): self
    {
        return new self(BookingFailure::NotConnected);
    }

    public static function bookingNotAllowed(?string $fallbackUrl): self
    {
        return new self(BookingFailure::BookingNotAllowed, $fallbackUrl);
    }

    public static function slotTaken(): self
    {
        return new self(BookingFailure::SlotTaken);
    }

    public static function unavailable(?Throwable $previous = null): self
    {
        return new self(BookingFailure::Unavailable, previous: $previous);
    }
}
