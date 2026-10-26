<?php

declare(strict_types=1);

namespace Domain\Booking\Actions;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Booking\Services\BookingWindow;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;

/**
 * The free times of a shown calendar for the week the visitor browses to,
 * clamped by {@see BookingWindow}. A calendar already booked or expired has
 * no times to offer.
 */
class ListBookingSlotsAction
{
    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly BookingInstanceMeetingType $meetingType,
    ) {}

    /**
     * @return list<CarbonImmutable>
     *
     * @throws FormNotClaimableException when the calendar was booked or has expired
     * @throws BookingException
     */
    public function execute(FormSubmission $instance, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! $instance->isPending()) {
            throw FormNotClaimableException::alreadySubmitted();
        }

        if ($instance->isExpired()) {
            throw FormNotClaimableException::expired();
        }

        [$from, $to] = BookingWindow::clamp($from, $to);

        if ($to->lessThanOrEqualTo($from)) {
            return [];
        }

        return $this->scheduler->availableTimes($instance->agent, $this->meetingType->of($instance), $from, $to);
    }
}
