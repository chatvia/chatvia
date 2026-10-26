<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Booking\Actions\RenderBookingCalendarAction;
use Domain\Booking\Exceptions\BookingException;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;

/**
 * A calendar is shown only for a real meeting type, once per visitor session,
 * and as a booking link where it cannot be drawn.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    $this->scheduler = FakeScheduler::install();
    $this->scheduler->slots = [CarbonImmutable::parse('2026-11-03T10:00:00Z')];
});

it('issues a pending booking instance with the first week of free times', function (): void {
    ['agent' => $agent, 'conversation' => $conversation, 'form' => $form] = CalendarAgent::seed();

    $outcome = app(RenderBookingCalendarAction::class)->execute($agent, $conversation, FakeScheduler::INTRO_CALL);

    $instance = FormSubmission::query()->where('external_id', $outcome->calendar?->bookingInstanceId)->firstOrFail();
    expect($outcome->calendar->eventTypeName)->toBe('Intro call')
        ->and($outcome->calendar->toArray()['slots'])->toBe([['start_time' => '2026-11-03T10:00:00Z']])
        ->and($outcome->calendar->toArray()['window_start'])->toBe('2026-11-02T09:01:00Z')
        ->and($outcome->calendar->toArray()['window_end'])->toBe('2026-11-09T09:00:00Z')
        ->and($instance->form_definition_id)->toBe($form->id)
        ->and($instance->status)->toBe(FormSubmissionStatus::Pending)
        ->and($instance->field(BookingInstanceMeetingType::KEY))->toBe(FakeScheduler::INTRO_CALL)
        ->and($instance->workspace_id)->toBe($agent->workspace_id);
});

it('matches a meeting type by its name', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();

    expect(app(RenderBookingCalendarAction::class)->execute($agent, $conversation, 'intro call')->calendar)->not->toBeNull();
});

it('lists the real meeting types for an unknown one, issuing nothing', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();

    $outcome = app(RenderBookingCalendarAction::class)->execute($agent, $conversation, 'Board meeting');

    expect($outcome->calendar)->toBeNull()
        ->and($outcome->meetingTypeNames)->toBe(['Intro call'])
        ->and(FormSubmission::query()->count())->toBe(0);
});

it('refuses a second booking in the same visitor session', function (): void {
    ['agent' => $agent, 'conversation' => $earlier, 'form' => $form] = CalendarAgent::seed();
    FormSubmission::factory()->forDefinition($form)->submitted()->create([
        'chat_conversation_id' => $earlier->id,
        'values' => ['start_time' => '2026-11-03T10:00:00Z'],
    ]);
    $later = Conversation::factory()->create(['agent_id' => $agent->id, 'chat_session_id' => $earlier->chat_session_id, 'source' => ConversationSource::Widget]);

    $outcome = app(RenderBookingCalendarAction::class)->execute($agent, $later, FakeScheduler::INTRO_CALL);

    expect($outcome->alreadyBooked)->toBeTrue()
        ->and($outcome->bookedStartTime)->toBe('2026-11-03T10:00:00Z')
        ->and($outcome->calendar)->toBeNull();
});

it('gives the booking page on a messaging channel', function (): void {
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed(ConversationSource::Telegram);

    $outcome = app(RenderBookingCalendarAction::class)->execute($agent, $conversation, FakeScheduler::INTRO_CALL);

    expect($outcome->bookingUrl)->toBe('https://calendly.com/acme/intro')
        ->and($outcome->calendar)->toBeNull()
        ->and(FormSubmission::query()->count())->toBe(0);
});

it('gives the booking page for a type the chat cannot book', function (): void {
    $this->scheduler->types = [FakeScheduler::meetingType(bookable: false)];
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();

    expect(app(RenderBookingCalendarAction::class)->execute($agent, $conversation, FakeScheduler::INTRO_CALL)->bookingUrl)
        ->toBe('https://calendly.com/acme/intro');
});

it('passes on a scheduler that is not connected', function (): void {
    $this->scheduler->failWith = BookingException::notConnected();
    ['agent' => $agent, 'conversation' => $conversation] = CalendarAgent::seed();

    app(RenderBookingCalendarAction::class)->execute($agent, $conversation, FakeScheduler::INTRO_CALL);
})->throws(BookingException::class);
