<?php

declare(strict_types=1);

namespace Domain\Booking\Services;

use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Contracts\Schedulers;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Models\FormSubmission;

/**
 * The meeting type a booking instance was shown for, and the scheduler it was
 * shown from. The pending row keeps both ids; the details are read fresh from
 * that scheduler (cached there), so a type removed since cannot be booked,
 * and switching the agent's scheduler never breaks a calendar already shown.
 */
class BookingInstanceMeetingType
{
    /** Where a pending booking instance keeps its meeting type. */
    public const string KEY = 'event_type';

    /** Where a pending booking instance keeps its scheduler's connector slug. */
    public const string PROVIDER_KEY = 'provider';

    public function __construct(private readonly Schedulers $schedulers) {}

    /**
     * @throws BookingException
     */
    public function of(FormSubmission $instance): MeetingTypeData
    {
        $uri = $instance->field(self::KEY);

        return collect($this->schedulerOf($instance)->meetingTypes($instance->agent))
            ->first(fn (MeetingTypeData $type): bool => $type->uri === $uri)
            ?? throw BookingException::unavailable();
    }

    /**
     * @throws BookingException
     */
    public function schedulerOf(FormSubmission $instance): Scheduler
    {
        $provider = $instance->field(self::PROVIDER_KEY);

        return filled($provider) ? $this->schedulers->using($provider) : $this->schedulers->forAgent($instance->agent);
    }
}
