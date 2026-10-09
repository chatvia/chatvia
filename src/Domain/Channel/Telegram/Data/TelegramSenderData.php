<?php

namespace Domain\Channel\Telegram\Data;

/**
 * Who sent an inbound Telegram message, as far as the bot can see: the
 * self-set first name (titles a new conversation and fills the `{name}`
 * command placeholder) and the optional @username.
 */
final readonly class TelegramSenderData
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $username = null,
    ) {}

    /**
     * Wrap the `message.from` fields as the webhook (and the queued job)
     * carry them.
     *
     * @param  array{first_name?: string|null, username?: string|null}  $from
     */
    public static function fromArray(array $from): self
    {
        return new self(
            firstName: $from['first_name'] ?? null,
            username: $from['username'] ?? null,
        );
    }

    /**
     * The shape the queued job carries.
     *
     * @return array{first_name: string|null, username: string|null}
     */
    public function toArray(): array
    {
        return ['first_name' => $this->firstName, 'username' => $this->username];
    }
}
