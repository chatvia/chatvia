<?php

declare(strict_types=1);

namespace Domain\Channel\Teams\Data;

/**
 * One Bot Framework activity as the webhook received it, cut down to what the
 * receive step decides on: its id (to answer it once), its type, who sent it
 * (a bot's own replies must never be answered) and whether it carries text.
 * The message a turn needs travels on as {@see TeamsActivityData}.
 */
final readonly class TeamsInboundActivityData
{
    public function __construct(
        public ?string $id,
        public ?string $type,
        public ?string $fromId,
        public ?string $fromRole,
        public ?string $recipientId,
        public bool $hasText,
        public TeamsActivityData $message,
    ) {}

    /**
     * @param  array<string, mixed>  $activity  the activity as Microsoft delivered it
     */
    public static function fromPayload(array $activity): self
    {
        return new self(
            id: filled($activity['id'] ?? null) ? (string) $activity['id'] : null,
            type: $activity['type'] ?? null,
            fromId: $activity['from']['id'] ?? null,
            fromRole: $activity['from']['role'] ?? null,
            recipientId: $activity['recipient']['id'] ?? null,
            hasText: filled($activity['text'] ?? null),
            message: TeamsActivityData::fromArray($activity),
        );
    }
}
