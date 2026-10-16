<?php

namespace Domain\Channel\Messenger\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A Facebook Page awaiting the workspace's choice after the Messenger OAuth
 * round-trip — id and name only; the page tokens stay server-side in the
 * session.
 */
#[TypeScript]
class MessengerPagePickData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    /**
     * @param  array<string, string>  $pick
     */
    public static function fromArray(array $pick): self
    {
        return new self(
            id: (string) $pick['id'],
            name: (string) $pick['name'],
        );
    }
}
