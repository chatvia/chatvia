<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Models\FormSubmission;

/**
 * The meeting type a booking instance was shown for. The pending row keeps
 * its id; the details are read fresh from the scheduler (cached there), so a
 * type removed since cannot be booked.
 */
class BookingInstanceMeetingType
{
    /** Where a pending booking instance keeps its meeting type. */
    public const string KEY = 'event_type';

    public function __construct(private readonly Scheduler $scheduler) {}

    /**
     * @throws BookingException
     */
    public function of(FormSubmission $instance): MeetingTypeData
    {
        $uri = $instance->field(self::KEY);

        return collect($this->scheduler->meetingTypes($instance->agent))
            ->first(fn (MeetingTypeData $type): bool => $type->uri === $uri)
            ?? throw BookingException::unavailable();
    }
}
