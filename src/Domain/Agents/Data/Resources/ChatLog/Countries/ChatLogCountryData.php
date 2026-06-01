<?php

namespace Domain\Agents\Data\Resources\ChatLog\Countries;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One country that's appeared at least once as a visitor's origin on the
 * agent's chat — drives the country filter on the chat-log page.
 */
#[TypeScript]
class ChatLogCountryData extends Data
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
    ) {}
}
