<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\MeetingTypeData;
use Domain\Booking\Enums\BookingFailure;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\CalendlyScheduler;
use Domain\Integration\Mcp\Data\McpToolResultData;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpClient;
use Tests\Support\Booking\FakeScheduler;

/**
 * The Calendly scheduler calls Calendly's MCP tools on the workspace's
 * Calendly connection and reads their API-shaped replies. The MCP client is
 * faked: each test records the calls and answers them.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->server = AgentMcpServer::factory()->create([
        'workspace_id' => $this->agent->workspace_id,
        'connector_slug' => 'calendly',
        'slug' => 'calendly',
        'is_enabled' => true,
        'last_error' => null,
    ]);
    $this->calls = [];
    $this->replies = [];

    $client = Mockery::mock(McpClient::class);
    $client->shouldReceive('callTool')->andReturnUsing(function (AgentMcpServer $server, string $tool, array $arguments): McpToolResultData {
        $this->calls[] = ['server' => $server->id, 'tool' => $tool, 'arguments' => $arguments];
        $reply = $this->replies[$tool] ?? ['structuredContent' => []];

        if ($reply instanceof Throwable) {
            throw $reply;
        }

        return new McpToolResultData((string) ($reply['text'] ?? ''), (bool) ($reply['isError'] ?? false), $reply['structuredContent'] ?? null);
    });
    app()->instance(McpClient::class, $client);
});

it('lists the active, public event types of the connected user', function (): void {
    $this->replies['users-get_current_user'] = ['structuredContent' => ['resource' => ['uri' => 'https://api.calendly.com/users/u1']]];
    $this->replies['event_types-list_event_types'] = ['structuredContent' => ['collection' => [
        ['uri' => 'https://api.calendly.com/event_types/a', 'name' => 'Intro', 'duration' => 30, 'active' => true],
        ['uri' => 'https://api.calendly.com/event_types/b', 'name' => 'Secret', 'duration' => 30, 'active' => true, 'secret' => true],
    ]]];

    $types = app(CalendlyScheduler::class)->meetingTypes($this->agent);

    expect(array_map(fn (MeetingTypeData $type): string => $type->name, $types))->toBe(['Intro'])
        ->and($this->calls[1])->toBe(['server' => $this->server->id, 'tool' => 'event_types-list_event_types', 'arguments' => ['user' => 'https://api.calendly.com/users/u1', 'active' => true, 'count' => 100]]);
});

it('reads the free start times in UTC, in order', function (): void {
    $this->replies['event_types-list_event_type_available_times'] = ['structuredContent' => ['collection' => [
        ['status' => 'available', 'start_time' => '2026-11-03T12:00:00.000000Z'],
        ['status' => 'available', 'start_time' => '2026-11-03T10:00:00.000000Z'],
        ['status' => 'unavailable', 'start_time' => '2026-11-03T11:00:00.000000Z'],
    ]]];

    $slots = app(CalendlyScheduler::class)->availableTimes(
        $this->agent,
        FakeScheduler::meetingType(),
        CarbonImmutable::parse('2026-11-02T09:00:00Z'),
        CarbonImmutable::parse('2026-11-09T09:00:00Z'),
    );

    expect(array_map(fn (CarbonImmutable $slot): string => $slot->toIso8601ZuluString(), $slots))->toBe(['2026-11-03T10:00:00Z', '2026-11-03T12:00:00Z'])
        ->and($this->calls[0]['arguments'])->toBe([
            'event_type' => FakeScheduler::INTRO_CALL,
            'start_time' => '2026-11-02T09:00:00Z',
            'end_time' => '2026-11-09T09:00:00Z',
        ]);
});

function csRequest(): BookingRequestData
{
    return new BookingRequestData(CarbonImmutable::parse('2026-11-03T10:00:00Z'), 'Ada Lovelace', 'ada@example.test', 'Europe/London', 'About the API');
}

it('books with the location and the notes answer', function (): void {
    $this->replies['meetings-create_invitee'] = ['structuredContent' => ['resource' => ['uri' => 'https://api.calendly.com/scheduled_events/e1/invitees/i1', 'event' => 'https://api.calendly.com/scheduled_events/e1']]];

    $booked = app(CalendlyScheduler::class)->book($this->agent, FakeScheduler::meetingType(), csRequest());

    expect($booked->eventUri)->toBe('https://api.calendly.com/scheduled_events/e1')
        ->and($this->calls[0]['arguments'])->toBe(['post_invitee_request' => [
            'event_type' => FakeScheduler::INTRO_CALL,
            'start_time' => '2026-11-03T10:00:00Z',
            'invitee' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'timezone' => 'Europe/London'],
            'location' => ['kind' => 'google_conference'],
            'questions_and_answers' => [['question' => 'Anything we should know?', 'position' => 0, 'answer' => 'About the API']],
        ]]);
});

it('reads why Calendly refused a booking', function (string $text, BookingFailure $reason): void {
    $this->replies['meetings-create_invitee'] = ['isError' => true, 'text' => $text];

    try {
        app(CalendlyScheduler::class)->book($this->agent, FakeScheduler::meetingType(), csRequest());
        $this->fail('The booking should have been refused.');
    } catch (BookingException $e) {
        expect($e->reason)->toBe($reason);

        if ($reason === BookingFailure::BookingNotAllowed) {
            expect($e->fallbackUrl)->toBe('https://calendly.com/acme/intro');
        }
    }
})->with([
    'a free plan' => ['Error 403: Permission Denied. This feature requires a paid plan.', BookingFailure::BookingNotAllowed],
    'a taken time' => ['The selected time is no longer available.', BookingFailure::SlotTaken],
    'anything else' => ['Internal error', BookingFailure::Unavailable],
]);

it('is unavailable when Calendly cannot be reached', function (): void {
    $this->replies['event_types-list_event_type_available_times'] = new McpException('timeout');

    app(CalendlyScheduler::class)->availableTimes($this->agent, FakeScheduler::meetingType(), now(), now()->addDay());
})->throws(BookingException::class, 'The calendar could not be reached');

it('is not connected without a working Calendly connection', function (): void {
    $this->server->update(['last_error' => 'Token revoked']);

    app(CalendlyScheduler::class)->meetingTypes($this->agent);
})->throws(BookingException::class, 'Booking is not set up');
