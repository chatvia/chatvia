<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Booking\Actions\BookMeetingAction;
use Domain\Booking\Data\BookingRequestData;
use Domain\Booking\Data\BookMeetingData;
use Domain\Booking\Enums\BookingFailure;
use Domain\Booking\Exceptions\BookingException;
use Domain\Conversation\Models\Message;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Events\FormSubmitted;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\Event;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;

/**
 * Booking claims the calendar once, books with the scheduler, stores the
 * booking as a lead and marks the chat's calendar booked. A refused booking
 * leaves the calendar bookable.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    Event::fake([FormSubmitted::class]);
    $this->scheduler = FakeScheduler::install();
    ['form' => $this->form, 'conversation' => $this->conversation] = CalendarAgent::seed();
    $this->instance = CalendarAgent::pendingInstance($this->form, $this->conversation);
});

function bmaBook(FormSubmission $instance, mixed $conversation, ?string $messageId = null): mixed
{
    return app(BookMeetingAction::class)->execute(new BookMeetingData(
        submission: $instance,
        conversation: $conversation,
        request: new BookingRequestData(CarbonImmutable::parse('2026-11-03T10:00:00Z'), 'Ada Lovelace', 'ada@example.test', 'Europe/London', 'About the API'),
        assistantMessageExternalId: $messageId,
        ip: '203.0.113.5',
        userAgent: 'Pest',
    ));
}

it('books the meeting and stores it as a lead', function (): void {
    $result = bmaBook($this->instance, $this->conversation);

    $lead = $this->instance->fresh();
    expect($result->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($result->startTime->toIso8601ZuluString())->toBe('2026-11-03T10:00:00Z')
        ->and($this->scheduler->booked)->toHaveCount(1)
        ->and($lead->status)->toBe(FormSubmissionStatus::Submitted)
        ->and($lead->values)->toEqual([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'meeting_type' => 'Intro call',
            'start_time' => '2026-11-03T10:00:00Z',
            'timezone' => 'Europe/London',
            'notes' => 'About the API',
        ])
        ->and($lead->ip_address)->toBe('203.0.113.5');

    Event::assertDispatched(FormSubmitted::class, fn (FormSubmitted $event): bool => $event->submission->source === 'calendar'
        && $event->submission->formSlug === 'meeting-bookings'
        && $event->submission->values['email'] === 'ada@example.test');
});

it('marks the message\'s calendar booked, without the visitor\'s details', function (): void {
    $message = Message::factory()->create([
        'chat_conversation_id' => $this->conversation->id,
        'metadata' => ['artifacts' => [['kind' => 'calendar', 'data' => ['booking_instance_id' => $this->instance->external_id, 'status' => 'pending', 'booked_start_time' => null]]]],
    ]);

    bmaBook($this->instance, $this->conversation, $message->external_id);

    expect($message->fresh()->metadata['artifacts'][0]['data'])->toEqual([
        'booking_instance_id' => $this->instance->external_id,
        'status' => 'submitted',
        'booked_start_time' => '2026-11-03T10:00:00Z',
    ])->and($this->instance->fresh()->chat_message_id)->toBe($message->id);
});

it('books only once', function (): void {
    bmaBook($this->instance, $this->conversation);

    expect(fn (): mixed => bmaBook($this->instance, $this->conversation))->toThrow(FormNotClaimableException::class)
        ->and($this->scheduler->booked)->toHaveCount(1);
});

it('refuses an expired calendar without booking', function (): void {
    $this->instance->update(['expires_at' => now()->subMinute()]);

    expect(fn (): mixed => bmaBook($this->instance, $this->conversation))->toThrow(FormNotClaimableException::class)
        ->and($this->scheduler->booked)->toBe([])
        ->and($this->instance->fresh()->status)->not->toBe(FormSubmissionStatus::Submitted);
});

it('keeps the calendar bookable when the scheduler refuses', function (): void {
    $this->scheduler->failWith = BookingException::slotTaken();

    try {
        bmaBook($this->instance, $this->conversation);
        $this->fail('The booking should have been refused.');
    } catch (BookingException $e) {
        expect($e->reason)->toBe(BookingFailure::SlotTaken);
    }

    expect($this->instance->fresh()->status)->toBe(FormSubmissionStatus::Pending);
    Event::assertNotDispatched(FormSubmitted::class);
});

it('books with the scheduler the calendar was shown from, not the agent\'s current one', function (): void {
    $this->scheduler->provider = 'calendly';
    $instance = CalendarAgent::pendingInstance($this->form, $this->conversation, provider: 'cal-com');

    bmaBook($instance, $this->conversation);

    expect($this->scheduler->used)->toBe(['cal-com', 'cal-com'])
        ->and($this->scheduler->booked)->toHaveCount(1);
});
