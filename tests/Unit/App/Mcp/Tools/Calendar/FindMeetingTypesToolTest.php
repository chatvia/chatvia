<?php

declare(strict_types=1);

use App\Mcp\Servers\CalendarServer;
use App\Mcp\Tools\Calendar\FindMeetingTypesTool;
use Domain\Booking\Exceptions\BookingException;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Mcp\InternalMcp;

/**
 * find-meeting-types tells the model which meetings can be booked, or to
 * help another way when the calendar is not available.
 */
beforeEach(function (): void {
    $this->scheduler = FakeScheduler::install();
    ['agent' => $this->agent] = CalendarAgent::seed();
    InternalMcp::actAsAgent($this->agent->external_id, path: '/mcp/calendar');
});

it('lists the meeting types with their ids', function (): void {
    $result = InternalMcp::result(CalendarServer::tool(FindMeetingTypesTool::class));

    expect($result['structuredContent']['meeting_types'])->toBe([[
        'id' => FakeScheduler::INTRO_CALL,
        'name' => 'Intro call',
        'duration_minutes' => 30,
        'description' => 'A first chat about your needs.',
    ]]);
});

it('says there is nothing to book when there are no types', function (): void {
    $this->scheduler->types = [];

    $result = InternalMcp::result(CalendarServer::tool(FindMeetingTypesTool::class));

    expect($result['structuredContent']['meeting_types'])->toBe([])
        ->and($result['content'][0]['text'])->toContain('no meetings to book');
});

it('reports an error when the calendar is not set up', function (): void {
    $this->scheduler->failWith = BookingException::notConnected();

    $result = InternalMcp::result(CalendarServer::tool(FindMeetingTypesTool::class));

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('not available');
});
