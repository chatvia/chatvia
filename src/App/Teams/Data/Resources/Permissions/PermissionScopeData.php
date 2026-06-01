<?php

namespace App\Teams\Data\Resources\Permissions;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

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
