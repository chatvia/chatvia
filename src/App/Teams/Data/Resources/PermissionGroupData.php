<?php

namespace App\Teams\Data\Resources;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PermissionGroupData extends Data
{
    public function __construct(
        public string $label,
        public ?string $description,
        /** @var string[] */
        public array $permissions,
    ) {}

    public static function fromConfig(array $data): self
    {
        return new self(
            label: $data['label'],
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? [],
        );
    }
}
