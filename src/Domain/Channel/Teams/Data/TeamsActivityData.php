<?php

namespace Domain\Channel\Teams\Data;

/**
 * The parts of a Bot Framework message activity a channel turn needs: where to
 * reply (the conversation id and the regional `serviceUrl`), how to title a
 * new conversation (its type and the sender's name), and the message text with
 * the bot's own mention removed.
 */
final readonly class TeamsActivityData
{
    public function __construct(
        public string $conversationId,
        public string $serviceUrl,
        public string $text,
        public ?string $conversationType = null,
        public ?string $senderName = null,
    ) {}

    /**
     * Wrap a message activity as Microsoft delivers it. The webhook only
     * queues activities that carry a conversation id and a service URL.
     *
     * @param  array<string, mixed>  $activity
     */
    public static function fromArray(array $activity): self
    {
        return new self(
            conversationId: (string) ($activity['conversation']['id'] ?? ''),
            serviceUrl: (string) ($activity['serviceUrl'] ?? ''),
            text: self::textWithoutBotMention($activity),
            conversationType: $activity['conversation']['conversationType'] ?? null,
            senderName: $activity['from']['name'] ?? null,
        );
    }

    /**
     * In channels and group chats the text arrives as `<at>Bot Name</at>
     * question…`. Drop the bot's own mention(s) — identified through the
     * activity's mention entities addressed to the bot (`recipient`) — and
     * unwrap any remaining `<at>` markup around other people's names so the
     * model sees plain text.
     *
     * @param  array<string, mixed>  $activity
     */
    private static function textWithoutBotMention(array $activity): string
    {
        $text = $activity['text'] ?? '';

        if (blank($text)) {
            return '';
        }

        $recipientId = $activity['recipient']['id'] ?? null;

        foreach ((array) ($activity['entities'] ?? []) as $entity) {
            if (($entity['type'] ?? null) === 'mention'
                && ($entity['mentioned']['id'] ?? null) === $recipientId
                && filled($entity['text'] ?? null)) {
                $text = str_replace($entity['text'], '', $text);
            }
        }

        $text = preg_replace('#</?at>#', '', $text) ?? $text;

        return trim($text);
    }
}
