<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * One kind of meeting a visitor can book (a Calendly event type), with what a
 * booking through the API must send back: the location and the question the
 * visitor's notes answer. A type whose booking needs answers the chat does
 * not ask for is not bookable in the chat; the visitor gets its booking page.
 */
final readonly class MeetingTypeData
{
    /** Location kinds that need input from the invitee the chat does not collect. */
    private const array INVITEE_LOCATION_KINDS = ['ask_invitee', 'outbound_call'];

    /** Calendly question types a free-text note can answer. */
    private const array TEXT_QUESTION_TYPES = ['string', 'text'];

    /**
     * @param  array{kind: string, location?: string}|null  $location  sent with the booking; null lets the scheduler pick
     * @param  array{question: string, position: int}|null  $notesQuestion  the question the visitor's notes answer
     */
    public function __construct(
        public string $uri,
        public string $name,
        public int $durationMinutes,
        public ?string $description,
        public ?string $schedulingUrl,
        public ?array $location,
        public ?array $notesQuestion,
        public bool $notesRequired,
        public bool $bookableInChat,
    ) {}

    /**
     * @param  array<string, mixed>  $row  one entry of Calendly's event type collection
     */
    public static function fromCalendly(array $row): self
    {
        $locations = array_values(array_filter((array) ($row['locations'] ?? []), is_array(...)));
        $first = $locations[0] ?? null;
        $roundRobin = filled($row['pooling_type'] ?? null);

        $location = null;
        if (! $roundRobin && $first !== null && filled($first['kind'] ?? null)) {
            $location = array_filter(
                ['kind' => (string) $first['kind'], 'location' => filled($first['location'] ?? null) ? (string) $first['location'] : null],
                filled(...),
            );
        }

        $questions = collect((array) ($row['custom_questions'] ?? []))
            ->filter(fn (mixed $question): bool => is_array($question) && ($question['enabled'] ?? false) === true)
            ->sortBy('position')
            ->values();

        $notes = $questions->first(fn (array $question): bool => in_array($question['type'] ?? null, self::TEXT_QUESTION_TYPES, true));
        $otherRequired = $questions->contains(fn (array $question): bool => ($question['required'] ?? false) === true && $question !== $notes);

        return new self(
            uri: (string) ($row['uri'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            durationMinutes: (int) ($row['duration'] ?? 0),
            description: filled($row['description_plain'] ?? null) ? Str::limit((string) $row['description_plain'], 300) : null,
            schedulingUrl: filled($row['scheduling_url'] ?? null) ? (string) $row['scheduling_url'] : null,
            location: $location,
            notesQuestion: $notes === null ? null : ['question' => (string) $notes['name'], 'position' => (int) ($notes['position'] ?? 0)],
            notesRequired: ($notes['required'] ?? false) === true,
            bookableInChat: ! $otherRequired
                && ! in_array(Arr::get($location ?? [], 'kind'), self::INVITEE_LOCATION_KINDS, true),
        );
    }
}
