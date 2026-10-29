<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Booking\Actions\RenderBookingCalendarAction;
use Domain\Booking\Services\BookingInstanceMeetingType;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormSubmission;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;

/**
 * Showing a calendar issues a pending booking instance with the first week
 * of free times, and is refused once the visitor session already booked.
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
        ->and($instance->field(BookingInstanceMeetingType::PROVIDER_KEY))->toBe('calendly')
        ->and($instance->workspace_id)->toBe($agent->workspace_id);
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
