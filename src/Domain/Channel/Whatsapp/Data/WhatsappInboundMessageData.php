<?php

namespace Domain\Channel\Whatsapp\Data;

/**
 * One plain-text message a WhatsApp user sent to a business number, as
 * extracted from a WhatsApp Cloud API webhook delivery. `messageId` is the
 * wamid (the handle for the read receipt + typing indicator); `senderName` is
 * the user's self-set profile name, delivered alongside the message.
 */
final readonly class WhatsappInboundMessageData
{
    public function __construct(
        public string $phoneNumberId,
        public string $waId,
        public string $text,
        public string $messageId,
        public ?string $senderName,
    ) {}

    /**
     * The user text messages in a webhook body (`object =
     * whatsapp_business_account`). Events arrive batched — entry[] per WABA,
     * changes[] per field, messages[] per sender; only `messages` changes
     * carry user messages. Everything that isn't a text message is dropped:
     * statuses (delivery receipts for our own sends arrive under
     * value.statuses, so there is no echo loop), media, reactions, system
     * events.
     *
     * @param  array<string, mixed>  $payload  the decoded webhook body
     * @return list<self>
     */
    public static function listFromWebhook(array $payload): array
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return [];
        }

        $messages = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (is_array($change) && ($change['field'] ?? null) === 'messages') {
                    array_push($messages, ...self::fromChangeValue((array) ($change['value'] ?? [])));
                }
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<self>
     */
    private static function fromChangeValue(array $value): array
    {
        $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
        $names = self::contactNames($value);
        $messages = [];

        foreach ((array) ($value['messages'] ?? []) as $message) {
            if (! is_array($message) || ! self::isText($message)) {
                continue;
            }

            $waId = (string) $message['from'];

            $messages[] = new self(
                phoneNumberId: $phoneNumberId,
                waId: $waId,
                text: (string) $message['text']['body'],
                messageId: (string) $message['id'],
                senderName: $names[$waId] ?? null,
            );
        }

        return $messages;
    }

    /**
     * Map wa_id → profile name, so the conversation gets a readable title.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, string>
     */
    private static function contactNames(array $value): array
    {
        $names = [];

        foreach ((array) ($value['contacts'] ?? []) as $contact) {
            if (is_array($contact) && isset($contact['wa_id'], $contact['profile']['name'])) {
                $names[(string) $contact['wa_id']] = (string) $contact['profile']['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private static function isText(array $message): bool
    {
        if (($message['type'] ?? null) !== 'text' || ! isset($message['id'], $message['from'])) {
            return false;
        }

        $text = $message['text']['body'] ?? null;

        return filled($text);
    }
}
