<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use Laravel\Passkeys\Passkey;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One passkey on the account's passkeys page. Adding and removing passkeys go
 * through the authentication package's own routes, which address them by id.
 */
#[TypeScript]
class PasskeyRowData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $last_used_at,
        public ?string $created_at,
    ) {}

    public static function fromModel(Passkey $passkey): self
    {
        return new self(
            id: $passkey->id,
            name: $passkey->name,
            last_used_at: $passkey->last_used_at?->toIso8601String(),
            created_at: $passkey->created_at?->toIso8601String(),
        );
    }
}
