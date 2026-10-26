<?php

declare(strict_types=1);

namespace Domain\Booking\Data;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Domain\Form\Enums\FormSubmissionStatus;

/**
 * A booking calendar issued into a conversation: the meeting type, the first
 * window of free times and the booking instance the visitor books against.
 * {@see toArray()} is the `calendar` artifact the chat draws; times are UTC.
 */
final readonly class CalendarArtifactData
{
    /**
     * @param  list<CarbonImmutable>  $slots
     */
    public function __construct(
        public string $bookingInstanceId,
        public string $eventTypeName,
        public int $durationMinutes,
        public ?string $locationKind,
        public array $slots,
        public CarbonInterface $windowStart,
        public CarbonInterface $windowEnd,
        public int $maxDaysAhead,
        public bool $notesRequired,
        public FormSubmissionStatus $status,
        public ?CarbonInterface $expiresAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'booking_instance_id' => $this->bookingInstanceId,
            'event_type_name' => $this->eventTypeName,
            'duration' => $this->durationMinutes,
            'location_kind' => $this->locationKind,
            'slots' => array_map(static fn (CarbonImmutable $slot): array => ['start_time' => $slot->toIso8601ZuluString()], $this->slots),
            'window_start' => $this->windowStart->toIso8601ZuluString(),
            'window_end' => $this->windowEnd->toIso8601ZuluString(),
            'max_days_ahead' => $this->maxDaysAhead,
            'notes_required' => $this->notesRequired,
            'status' => $this->status->value,
            'booked_start_time' => null,
            'expires_at' => $this->expiresAt?->toIso8601ZuluString(),
        ];
    }
}
