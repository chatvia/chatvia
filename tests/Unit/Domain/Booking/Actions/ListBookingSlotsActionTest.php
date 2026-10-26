<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Domain\Booking\Actions\ListBookingSlotsAction;
use Domain\Booking\Exceptions\BookingException;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Exceptions\FormNotClaimableException;
use Tests\Support\Booking\CalendarAgent;
use Tests\Support\Booking\FakeScheduler;

/**
 * Browsing a shown calendar asks the scheduler for one clamped week at a
 * time, and only while the calendar can still be booked.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 11, 2, 9, 0, 0));
    $this->scheduler = FakeScheduler::install();
    ['form' => $form, 'conversation' => $conversation] = CalendarAgent::seed();
    $this->instance = CalendarAgent::pendingInstance($form, $conversation);
});

it('returns the free times of the asked week', function (): void {
    $this->scheduler->slots = [CarbonImmutable::parse('2026-11-10T10:00:00Z')];

    $slots = app(ListBookingSlotsAction::class)->execute($this->instance, now()->addDays(7), now()->addDays(14));

    expect($slots)->toHaveCount(1)
        ->and($this->scheduler->asked)->toBe([['from' => '2026-11-09T09:00:00Z', 'to' => '2026-11-16T09:00:00Z']]);
});

it('clamps the range to the future, one week, and the furthest day', function (): void {
    app(ListBookingSlotsAction::class)->execute($this->instance, now()->subDays(3), now()->addDays(20));
    app(ListBookingSlotsAction::class)->execute($this->instance, now()->addDays(28), now()->addDays(40));

    expect($this->scheduler->asked)->toBe([
        ['from' => '2026-11-02T09:01:00Z', 'to' => '2026-11-09T09:01:00Z'],
        ['from' => '2026-11-30T09:00:00Z', 'to' => '2026-12-03T09:00:00Z'],
    ]);
});

it('asks nothing for a range past the furthest day', function (): void {
    expect(app(ListBookingSlotsAction::class)->execute($this->instance, now()->addDays(40), now()->addDays(45)))->toBe([])
        ->and($this->scheduler->asked)->toBe([]);
});

it('refuses a booked calendar', function (): void {
    $this->instance->update(['status' => FormSubmissionStatus::Submitted]);

    app(ListBookingSlotsAction::class)->execute($this->instance, now(), now()->addDays(7));
})->throws(FormNotClaimableException::class);

it('refuses an expired calendar', function (): void {
    $this->instance->update(['expires_at' => now()->subMinute()]);

    app(ListBookingSlotsAction::class)->execute($this->instance, now(), now()->addDays(7));
})->throws(FormNotClaimableException::class);

it('fails when the meeting type is gone', function (): void {
    $this->scheduler->types = [];

    app(ListBookingSlotsAction::class)->execute($this->instance, now(), now()->addDays(7));
})->throws(BookingException::class);
