<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Account;

use Spatie\LaravelData\Data;

/**
 * An agent's identity and addresses, the short shape other parts of the app
 * pass around.
 */
class AgentData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $sub_domain,
        public string $url,
        public string $chat_url,
    ) {}
}
