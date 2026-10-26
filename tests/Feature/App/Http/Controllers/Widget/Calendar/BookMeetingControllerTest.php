<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Widget\WidgetHeaders;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;
use Tests\Support\Widget\EmbeddedWidget;

/**
 * POST /widget/calendar/{bookingInstance}/book books the time the visitor
 * picked in a calendar the agent showed in the widget. The calendar's
 * conversation must be reachable by the caller.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    Event::fake([FormSubmitted::class]);
    $this->scheduler = FakeScheduler::install();

    $this->agent = Agent::factory()->create(['is_public' => true]);
    EmbeddedWidget::allowSite($this->agent);
    $this->headers = EmbeddedWidget::chatHeaders($this->agent);
    ['form' => $form] = CalendarAgent::seed(agent: $this->agent);
    $this->instance = CalendarAgent::pendingInstance(
        $form,
        Conversation::query()->where('external_id', $this->headers[WidgetHeaders::CONVERSATION_ID])->firstOrFail(),
    );
});

function wbmBook(string $instanceId, array $headers): TestResponse
{
    return test()->withHeaders($headers)->postJson("/widget/calendar/{$instanceId}/book", [
        'start_time' => '2026-11-03T10:00:00Z',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'timezone' => 'Europe/London',
    ]);
}

it('books the picked time', function (): void {
    wbmBook($this->instance->external_id, $this->headers)
        ->assertOk()
        ->assertJsonPath('status', 'submitted');

    expect($this->instance->fresh()->status)->toBe(FormSubmissionStatus::Submitted);
});

it('answers 422 with the booking page when booking from the chat is not allowed', function (): void {
    $this->scheduler->failWith = BookingException::bookingNotAllowed('https://calendly.com/acme/intro');

    wbmBook($this->instance->external_id, $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('fallback_url', 'https://calendly.com/acme/intro');
});

it('refuses a caller outside the calendar\'s conversation', function (): void {
    $stranger = EmbeddedWidget::chatHeaders($this->agent);

    wbmBook($this->instance->external_id, $stranger)->assertForbidden();
    expect($this->scheduler->booked)->toBe([]);
});
