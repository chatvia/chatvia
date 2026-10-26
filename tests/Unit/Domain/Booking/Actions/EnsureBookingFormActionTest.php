<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Booking\Actions\EnsureBookingFormAction;
use Domain\Form\Enums\FormType;
use Domain\Form\Models\FormDefinition;

/**
 * Each agent gets one "Meeting bookings" form the Calendar capability
 * manages; its leads are the bookings.
 */
it('creates the managed bookings form once per agent', function (): void {
    $agent = Agent::factory()->create();

    $first = app(EnsureBookingFormAction::class)->execute($agent);
    $second = app(EnsureBookingFormAction::class)->execute($agent);

    expect($second->id)->toBe($first->id)
        ->and($first->managed_by)->toBe('calendar')
        ->and($first->form_type)->toBe(FormType::Booking)
        ->and($first->slug)->toBe('meeting-bookings')
        ->and(array_column($first->schema, 'key'))->toBe(['name', 'email', 'meeting_type', 'start_time', 'timezone', 'notes'])
        ->and(FormDefinition::query()->forAgent($agent)->count())->toBe(1);
});

it('picks a free slug when the owner already has a form with it', function (): void {
    $agent = Agent::factory()->create();
    FormDefinition::factory()->forAgent($agent)->create(['slug' => 'meeting-bookings']);

    expect(app(EnsureBookingFormAction::class)->execute($agent)->slug)->toBe('meeting-bookings-2');
});
