<?php

namespace Domain\Channel\Telegram\Data;

/**
 * The parts of a Telegram bot update an inbound turn needs: the `update_id`
 * (for de-duplication), the chat, the message text and who sent it.
 */
final readonly class TelegramUpdateData
{
    public function __construct(
        public ?string $updateId,
        public ?string $chatId,
        public ?string $text,
        public TelegramSenderData $sender = new TelegramSenderData,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the decoded update body
     */
    public static function fromPayload(array $payload): self
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        return new self(
            updateId: isset($payload['update_id']) ? (string) $payload['update_id'] : null,
            chatId: $chatId !== null ? (string) $chatId : null,
            text: filled($text) ? $text : null,
            sender: TelegramSenderData::fromArray([
                'first_name' => $message['from']['first_name'] ?? null,
                'username' => $message['from']['username'] ?? null,
            ]),
        );
    }
}
