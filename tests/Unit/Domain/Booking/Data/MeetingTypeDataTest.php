<?php

declare(strict_types=1);

use Domain\Booking\Data\MeetingTypeData;

/**
 * A Calendly or Cal.com event type is read into what a booking must send
 * back, and is bookable in the chat only when the chat collects every
 * required answer.
 */
function mtdRow(array $overrides = []): array
{
    return [
        'uri' => 'https://api.calendly.com/event_types/abc',
        'name' => '30 Minute Meeting',
        'duration' => 30,
        'description_plain' => 'A quick call.',
        'scheduling_url' => 'https://calendly.com/acme/30min',
        'pooling_type' => null,
        'locations' => [['kind' => 'google_conference', 'location' => null]],
        'custom_questions' => [
            ['name' => 'Please share anything that will help prepare for our meeting.', 'type' => 'text', 'position' => 0, 'enabled' => true, 'required' => false],
        ],
        ...$overrides,
    ];
}

it('reads the event type, its location and the notes question', function (): void {
    $type = MeetingTypeData::fromCalendly(mtdRow());

    expect($type->uri)->toBe('https://api.calendly.com/event_types/abc')
        ->and($type->durationMinutes)->toBe(30)
        ->and($type->location)->toBe(['kind' => 'google_conference'])
        ->and($type->notesQuestion)->toBe(['question' => 'Please share anything that will help prepare for our meeting.', 'position' => 0])
        ->and($type->notesRequired)->toBeFalse()
        ->and($type->bookableInChat)->toBeTrue();
});

it('is not bookable in the chat when the scheduler needs answers the chat does not collect', function (Closure $type): void {
    expect($type()->bookableInChat)->toBeFalse();
})->with([
    'Calendly, another required question' => [fn (): MeetingTypeData => MeetingTypeData::fromCalendly(mtdRow(['custom_questions' => [
        ['name' => 'Company size', 'type' => 'single_select', 'position' => 1, 'enabled' => true, 'required' => true],
    ]]))],
    'Calendly, the invitee gives the location' => [fn (): MeetingTypeData => MeetingTypeData::fromCalendly(mtdRow(['locations' => [['kind' => 'outbound_call']]]))],
    'Cal.com, a required custom field' => [fn (): MeetingTypeData => MeetingTypeData::fromCalcom(['id' => 1, 'title' => 'Call', 'slug' => 'call', 'bookingFields' => [['slug' => 'company', 'required' => true]]], 'ada')],
    'Cal.com, the attendee\'s phone as location' => [fn (): MeetingTypeData => MeetingTypeData::fromCalcom(['id' => 1, 'title' => 'Call', 'slug' => 'call', 'locations' => [['type' => 'attendeePhone']]], 'ada')],
]);
