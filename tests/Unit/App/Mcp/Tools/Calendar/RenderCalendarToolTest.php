<?php

declare(strict_types=1);

use App\Mcp\Servers\CalendarServer;
use App\Mcp\Tools\Calendar\RenderCalendarTool;
use Carbon\CarbonImmutable;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Mcp\InternalMcp;

/**
 * render-calendar returns the `calendar` artifact the chat draws, a booking
 * link where no calendar can be drawn, or guidance for the model.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    $this->scheduler = FakeScheduler::install();
    $this->scheduler->slots = [CarbonImmutable::parse('2026-11-03T10:00:00Z')];
});

function rctCall(string $meetingType = FakeScheduler::INTRO_CALL): array
{
    return InternalMcp::result(CalendarServer::tool(RenderCalendarTool::class, ['meeting_type' => $meetingType]));
}

it('renders a calendar artifact', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id, path: '/mcp/calendar');

    $result = rctCall();
    $calendar = $result['structuredContent']['calendar'];

    expect($result['content'][0]['text'])->toContain('booking calendar for "Intro call"')
        ->and($calendar['event_type_name'])->toBe('Intro call')
        ->and($calendar['duration'])->toBe(30)
        ->and($calendar['slots'])->toBe([['start_time' => '2026-11-03T10:00:00Z']])
        ->and($calendar['status'])->toBe('pending')
        ->and($calendar['max_days_ahead'])->toBe(31)
        ->and(FormSubmission::query()->where('external_id', $calendar['booking_instance_id'])->exists())->toBeTrue();
});

it('shares the booking link on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed(ConversationSource::Telegram);
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id, path: '/mcp/calendar');

    $result = rctCall();

    expect($result)->not->toHaveKey('structuredContent')
        ->and($result['content'][0]['text'])->toContain('https://calendly.com/acme/intro');
});

it('names the real meeting types for an unknown one', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id, path: '/mcp/calendar');

    $result = rctCall('Board meeting');

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('Intro call');
});

it('needs a conversation', function (): void {
    ['agent' => $agent] = CalendarAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, path: '/mcp/calendar');

    expect(rctCall()['isError'])->toBeTrue();
});

it('tells the model to help another way when booking is not set up', function (): void {
    $this->scheduler->failWith = BookingException::notConnected();
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();
    InternalMcp::actAsAgent($agent->external_id, $conversation->external_id, path: '/mcp/calendar');

    $result = rctCall();

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('not set up');
});
