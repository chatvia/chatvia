<?php

namespace Domain\Membership\Team\Data\Resources\Permissions;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A top-level permission category on the role editor (per-agent or
 * account-wide), built from config('team-permissions.scopes').
 */
#[TypeScript]
class PermissionScopeData extends Data
{
    public function __construct(
        public string $label,
        public string $description,
    ) {}

    public static function fromConfig(array $data): self
    {
        return new self(
            label: $data['label'],
            description: $data['description'],
        );
    }
}
