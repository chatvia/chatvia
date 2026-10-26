<?php

declare(strict_types=1);

use Domain\Booking\Data\MeetingTypeData;

/**
 * A Calendly event type is read into what a booking must send back, and is
 * bookable in the chat only when the chat collects every required answer.
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

it('keeps a physical location\'s address', function (): void {
    $type = MeetingTypeData::fromCalendly(mtdRow(['locations' => [['kind' => 'physical', 'location' => '1 Main St']]]));

    expect($type->location)->toBe(['kind' => 'physical', 'location' => '1 Main St']);
});

it('sends no location for a round-robin type', function (): void {
    expect(MeetingTypeData::fromCalendly(mtdRow(['pooling_type' => 'round_robin']))->location)->toBeNull();
});

it('is not bookable in the chat when another question is required', function (): void {
    $type = MeetingTypeData::fromCalendly(mtdRow(['custom_questions' => [
        ['name' => 'Notes', 'type' => 'text', 'position' => 0, 'enabled' => true, 'required' => false],
        ['name' => 'Company size', 'type' => 'single_select', 'position' => 1, 'enabled' => true, 'required' => true],
    ]]));

    expect($type->bookableInChat)->toBeFalse();
});

it('is not bookable in the chat when the invitee must give the location', function (): void {
    expect(MeetingTypeData::fromCalendly(mtdRow(['locations' => [['kind' => 'outbound_call']]]))->bookableInChat)->toBeFalse();
});

it('ignores a disabled question', function (): void {
    $type = MeetingTypeData::fromCalendly(mtdRow(['custom_questions' => [
        ['name' => 'Company size', 'type' => 'single_select', 'position' => 0, 'enabled' => false, 'required' => true],
    ]]));

    expect($type->bookableInChat)->toBeTrue()->and($type->notesQuestion)->toBeNull();
});

it('reads a Cal.com event type with its booking page', function (): void {
    $type = MeetingTypeData::fromCalcom([
        'id' => 42,
        'title' => 'Intro call',
        'slug' => 'intro',
        'lengthInMinutes' => 45,
        'description' => 'A first chat.',
        'locations' => [['type' => 'integration', 'integration' => 'cal-video']],
        'bookingFields' => [
            ['slug' => 'name', 'required' => true],
            ['slug' => 'email', 'required' => true],
            ['slug' => 'notes', 'required' => true],
        ],
    ], 'ada');

    expect($type->uri)->toBe('42')
        ->and($type->durationMinutes)->toBe(45)
        ->and($type->schedulingUrl)->toBe('https://cal.com/ada/intro')
        ->and($type->location)->toBe(['kind' => 'cal-video'])
        ->and($type->notesRequired)->toBeTrue()
        ->and($type->bookableInChat)->toBeTrue();
});

it('is not bookable in the chat when Cal.com needs other answers', function (array $overrides): void {
    expect(MeetingTypeData::fromCalcom(['id' => 1, 'title' => 'Call', 'slug' => 'call', ...$overrides], 'ada')->bookableInChat)->toBeFalse();
})->with([
    'a required custom field' => [['bookingFields' => [['slug' => 'company', 'required' => true]]]],
    'the attendee\'s phone as location' => [['locations' => [['type' => 'attendeePhone']]]],
]);
