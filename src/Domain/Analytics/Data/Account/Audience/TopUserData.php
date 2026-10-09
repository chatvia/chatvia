<?php

namespace Domain\Analytics\Data\Account\Audience;

/**
 * One identified end-user and how many messages they sent.
 */
final readonly class TopUserData
{
    public function __construct(
        public string $name,
        public ?string $email,
        public int $messages,
    ) {}
}
