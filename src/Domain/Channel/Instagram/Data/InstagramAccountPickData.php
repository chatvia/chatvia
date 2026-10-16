<?php

namespace Domain\Channel\Instagram\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An Instagram professional account awaiting the workspace's choice after the
 * Facebook OAuth round-trip — ig username + page name only; the page tokens
 * stay server-side in the session.
 */
#[TypeScript]
class InstagramAccountPickData extends Data
{
    public function __construct(
        public string $ig_id,
        public string $ig_username,
        public string $page_name,
    ) {}

    /**
     * @param  array<string, string>  $pick
     */
    public static function fromArray(array $pick): self
    {
        return new self(
            ig_id: (string) $pick['ig_id'],
            ig_username: (string) $pick['ig_username'],
            page_name: (string) $pick['page_name'],
        );
    }
}
