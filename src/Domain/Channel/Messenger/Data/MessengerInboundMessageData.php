<?php

namespace Domain\Channel\Messenger\Data;

/**
 * One plain-text message a Facebook user sent to a Page, as extracted from a
 * Messenger Platform webhook delivery.
 */
final readonly class MessengerInboundMessageData
{
    public function __construct(
        public string $pageId,
        public string $senderId,
        public string $text,
        public ?string $messageId,
    ) {}

    /**
     * The user text messages in a webhook body (`object = page`). Events
     * arrive batched — entry[] per Page, messaging[] per sender. Everything
     * that isn't a user's text message is dropped: delivery receipts,
     * postbacks, attachment-only messages, and the Page's own replies echoing
     * back (`is_echo`), or the bot would answer itself in a loop.
     *
     * @param  array<string, mixed>  $payload  the decoded webhook body
     * @return list<self>
     */
    public static function listFromWebhook(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'page') {
            return [];
        }

        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $pageId = (string) ($entry['id'] ?? '');

            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $message = is_array($event) ? self::fromEvent($pageId, $event) : null;

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
    private static function fromEvent(string $pageId, array $event): ?self
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
            pageId: $pageId,
            senderId: (string) $sender,
            text: $text,
            messageId: isset($message['mid']) ? (string) $message['mid'] : null,
        );
    }
}
