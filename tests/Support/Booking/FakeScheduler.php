<?php

declare(strict_types=1);

namespace Tests\Support\Booking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Domain\Agent\Models\Agent;
use Domain\Booking\Contracts\Scheduler;
use Domain\Booking\Data\BookedMeetingData;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Exceptions\BookingException;

/**
 * An in-memory {@see Scheduler}: one bookable "Intro call" by default, free
 * times the test sets, and a record of every range asked and booking made.
 * A test makes it fail by setting {@see $failWith}.
 */
final class FakeScheduler implements Scheduler
{
    public const string INTRO_CALL = 'https://api.calendly.com/event_types/intro-call';

    /** @var list<MeetingTypeData> */
    public array $types;

    /** @var list<CarbonImmutable> */
    public array $slots = [];

    /** @var list<array{from: string, to: string}> */
    public array $asked = [];

    /** @var list<BookingRequestData> */
    public array $booked = [];

    public ?BookingException $failWith = null;

    public function __construct()
    {
        $this->types = [self::meetingType()];
    }

    public static function install(): self
    {
        $fake = new self;
        app()->instance(Scheduler::class, $fake);

        return $fake;
    }

    public static function meetingType(string $uri = self::INTRO_CALL, string $name = 'Intro call', bool $bookable = true): MeetingTypeData
    {
        return new MeetingTypeData(
            uri: $uri,
            name: $name,
            durationMinutes: 30,
            description: 'A first chat about your needs.',
            schedulingUrl: 'https://calendly.com/acme/intro',
            location: ['kind' => 'google_conference'],
            notesQuestion: ['question' => 'Anything we should know?', 'position' => 0],
            notesRequired: false,
            bookableInChat: $bookable,
        );
    }

    public function meetingTypes(Agent $agent): array
    {
        $this->throwIfFailing();

        return $this->types;
    }

    public function availableTimes(Agent $agent, MeetingTypeData $type, CarbonInterface $from, CarbonInterface $to): array
    {
        $this->throwIfFailing();
        $this->asked[] = ['from' => $from->toIso8601ZuluString(), 'to' => $to->toIso8601ZuluString()];

        return $this->slots;
    }

    public function book(Agent $agent, MeetingTypeData $type, BookingRequestData $request): BookedMeetingData
    {
        $this->throwIfFailing();
        $this->booked[] = $request;

        return new BookedMeetingData($request->startTime, 'https://api.calendly.com/scheduled_events/e1', 'https://api.calendly.com/scheduled_events/e1/invitees/i1');
    }

    private function throwIfFailing(): void
    {
        if ($this->failWith instanceof BookingException) {
            throw $this->failWith;
        }
    }
}
