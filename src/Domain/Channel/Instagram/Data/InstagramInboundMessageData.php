<?php

namespace Domain\Channel\Instagram\Data;

/**
 * One plain-text Direct message an Instagram user sent to a professional
 * account, as extracted from an Instagram Messaging webhook delivery.
 */
final readonly class InstagramInboundMessageData
{
    public function __construct(
        public string $accountId,
        public string $senderId,
        public string $text,
        public ?string $messageId,
    ) {}

    /**
     * The user text messages in a webhook body (`object = instagram`). Events
     * arrive batched — entry[] per account (entry.id is the IG professional
     * account id; a payload variant without it falls back to the first event's
     * recipient), messaging[] per sender. Everything that isn't a user's text
     * message is dropped: read receipts, reactions, attachment-only messages
     * (story replies/mentions arrive as attachments), and the account's own
     * replies echoing back (`is_echo`).
     *
     * @param  array<string, mixed>  $payload  the decoded webhook body
     * @return list<self>
     */
    public static function listFromWebhook(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'instagram') {
            return [];
        }

        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $events = (array) ($entry['messaging'] ?? []);
            $accountId = (string) ($entry['id'] ?? '');

            if ($accountId === '' && isset($events[0]['recipient']['id'])) {
                $accountId = (string) $events[0]['recipient']['id'];
            }

            foreach ($events as $event) {
                $message = is_array($event) ? self::fromEvent($accountId, $event) : null;

                if ($message !== null) {
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function fromEvent(string $accountId, array $event): ?self
    {
        $message = $event['message'] ?? null;

        if (! is_array($message) || ($message['is_echo'] ?? false)) {
            return null;
        }

        $text = $message['text'] ?? null;
        $sender = $event['sender']['id'] ?? null;

        if (! is_string($text) || trim($text) === '' || $sender === null) {
            return null;
        }

        return new self(
            accountId: $accountId,
            senderId: (string) $sender,
            text: $text,
            messageId: isset($message['mid']) ? (string) $message['mid'] : null,
        );
    }
}
