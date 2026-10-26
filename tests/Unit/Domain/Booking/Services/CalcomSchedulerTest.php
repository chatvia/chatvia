<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Enums\BookingFailure;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\CalcomScheduler;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;

/**
 * The Cal.com scheduler calls Cal.com's MCP tools on the workspace's Cal.com
 * connection and reads their API v2 replies (`{status, data}`). The MCP
 * client is faked: each test records the calls and answers them.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->server = AgentMcpServer::factory()->create([
        'workspace_id' => $this->agent->workspace_id,
        'connector_slug' => 'cal-com',
        'slug' => 'cal-com',
        'is_enabled' => true,
        'last_error' => null,
    ]);
    $this->calls = [];
    $this->replies = [];

    $client = Mockery::mock(McpClient::class);
    $client->shouldReceive('callTool')->andReturnUsing(function (AgentMcpServer $server, string $tool, array $arguments): McpToolResultData {
        $this->calls[] = ['server' => $server->id, 'tool' => $tool, 'arguments' => $arguments];
        $reply = $this->replies[$tool] ?? ['structuredContent' => []];

        return new McpToolResultData((string) ($reply['text'] ?? ''), (bool) ($reply['isError'] ?? false), $reply['structuredContent'] ?? null);
    });
    app()->instance(McpClient::class, $client);
});

function ccsType(): MeetingTypeData
{
    return new MeetingTypeData('42', 'Intro call', 30, null, 'https://cal.com/ada/intro', ['kind' => 'cal-video'], null, false, true);
}

function ccsRequest(?string $notes = 'About the API'): BookingRequestData
{
    return new BookingRequestData(CarbonImmutable::parse('2026-11-03T10:00:00Z'), 'Ada Lovelace', 'ada@example.test', 'Europe/London', $notes);
}

it('lists the visible event types with their booking page', function (): void {
    $this->replies['get_me'] = ['structuredContent' => ['status' => 'success', 'data' => ['username' => 'ada']]];
    $this->replies['get_event_types'] = ['structuredContent' => ['status' => 'success', 'data' => [
        ['id' => 42, 'title' => 'Intro call', 'slug' => 'intro', 'lengthInMinutes' => 30, 'locations' => [['type' => 'integration', 'integration' => 'cal-video']]],
        ['id' => 43, 'title' => 'Hidden', 'slug' => 'hidden', 'lengthInMinutes' => 15, 'hidden' => true],
    ]]];

    $types = app(CalcomScheduler::class)->meetingTypes($this->agent);

    expect($types)->toHaveCount(1)
        ->and($types[0]->uri)->toBe('42')
        ->and($types[0]->schedulingUrl)->toBe('https://cal.com/ada/intro')
        ->and($types[0]->location)->toBe(['kind' => 'cal-video'])
        ->and(array_column($this->calls, 'tool'))->toBe(['get_me', 'get_event_types']);
});

it('flattens the day-keyed free times into UTC, in order', function (): void {
    $this->replies['get_availability'] = ['structuredContent' => ['status' => 'success', 'data' => [
        '2026-11-04' => [['start' => '2026-11-04T09:00:00.000Z']],
        '2026-11-03' => [['start' => '2026-11-03T12:00:00.000Z'], ['start' => '2026-11-03T10:00:00.000Z']],
    ]]];

    $slots = app(CalcomScheduler::class)->availableTimes($this->agent, ccsType(), CarbonImmutable::parse('2026-11-02T09:00:00Z'), CarbonImmutable::parse('2026-11-09T09:00:00Z'));

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->toIso8601ZuluString(), $slots))
        ->toBe(['2026-11-03T10:00:00Z', '2026-11-03T12:00:00Z', '2026-11-04T09:00:00Z'])
        ->and($this->calls[0]['arguments'])->toBe(['eventTypeId' => 42, 'start' => '2026-11-02T09:00:00Z', 'end' => '2026-11-09T09:00:00Z', 'timeZone' => 'UTC']);
});

it('books with the attendee and the notes', function (): void {
    $this->replies['create_booking'] = ['structuredContent' => ['status' => 'success', 'data' => ['uid' => 'bk_1', 'start' => '2026-11-03T10:00:00.000Z']]];

    $booked = app(CalcomScheduler::class)->book($this->agent, ccsType(), ccsRequest());

    expect($booked->eventUri)->toBe('bk_1')
        ->and($this->calls[0]['arguments'])->toBe([
            'eventTypeId' => 42,
            'start' => '2026-11-03T10:00:00Z',
            'attendee' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'timeZone' => 'Europe/London'],
            'bookingFieldsResponses' => ['notes' => 'About the API'],
        ]);
});

it('sends no notes field without notes', function (): void {
    app(CalcomScheduler::class)->book($this->agent, ccsType(), ccsRequest(null));

    expect($this->calls[0]['arguments'])->not->toHaveKey('bookingFieldsResponses');
});

it('reads why Cal.com refused a booking', function (string $text, BookingFailure $reason): void {
    $this->replies['create_booking'] = ['isError' => true, 'text' => $text];

    try {
        app(CalcomScheduler::class)->book($this->agent, ccsType(), ccsRequest());
        $this->fail('The booking should have been refused.');
    } catch (BookingException $e) {
        expect($e->reason)->toBe($reason);
    }
})->with([
    'a taken time' => ['no_available_users_found_error', BookingFailure::SlotTaken],
    'forbidden' => ['403 Forbidden', BookingFailure::BookingNotAllowed],
    'anything else' => ['Internal error', BookingFailure::Unavailable],
]);

it('is not connected without a working Cal.com connection', function (): void {
    $this->server->update(['is_enabled' => false]);

    app(CalcomScheduler::class)->meetingTypes($this->agent);
})->throws(BookingException::class, 'Booking is not set up');
