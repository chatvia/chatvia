<?php

declare(strict_types=1);

namespace Domain\Booking\Contracts;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Booking\Data\BookedMeetingData;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;

/**
 * The calendar service behind the Calendar capability: which meetings the
 * agent's workspace offers, when they are free, and booking one. Calendly and
 * Cal.com implement it ({@see Schedulers} picks one); the chat side never
 * depends on which.
 */
interface Scheduler
{
    /**
     * The workspace's active meeting types.
     *
     * @return list<MeetingTypeData>
     *
     * @throws BookingException
     */
    public function meetingTypes(Agent $agent): array;

    /**
     * Free start times of a meeting type within the range (UTC).
     *
     * @return list<CarbonImmutable>
     *
     * @throws BookingException
     */
    public function availableTimes(Agent $agent, MeetingTypeData $type, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * @throws BookingException
     */
    public function book(Agent $agent, MeetingTypeData $type, BookingRequestData $request): BookedMeetingData;
}
