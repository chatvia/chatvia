<?php

namespace Domain\Channel\Slack\Data;

/**
 * The parts of a Slack Events API callback an inbound turn needs: the
 * delivery's `event_id` (for de-duplication) and the inner event's kind,
 * channel, text, thread and sender.
 */
final readonly class SlackEventData
{
    public function __construct(
        public ?string $eventId,
        public ?string $type = null,
        public ?string $channelId = null,
        public ?string $text = null,
        public ?string $threadTs = null,
        public ?string $userId = null,
        public ?string $channelType = null,
        public bool $fromBot = false,
        public bool $hasSubtype = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the decoded callback body
     */
    public static function fromPayload(array $payload): self
    {
        $eventId = isset($payload['event_id']) ? (string) $payload['event_id'] : null;
        $event = $payload['event'] ?? null;

        if (! is_array($event)) {
            return new self($eventId);
        }

        return new self(
            eventId: $eventId,
            type: isset($event['type']) ? (string) $event['type'] : null,
            channelId: isset($event['channel']) ? (string) $event['channel'] : null,
            text: is_string($event['text'] ?? null) ? $event['text'] : null,
            threadTs: isset($event['thread_ts']) ? (string) $event['thread_ts'] : null,
            userId: isset($event['user']) ? (string) $event['user'] : null,
            channelType: isset($event['channel_type']) ? (string) $event['channel_type'] : null,
            fromBot: isset($event['bot_id']),
            hasSubtype: isset($event['subtype']),
        );
    }
}
