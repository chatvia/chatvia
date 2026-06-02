<?php

namespace Domain\Agent\Data\Account;

use Spatie\LaravelData\Data;

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
