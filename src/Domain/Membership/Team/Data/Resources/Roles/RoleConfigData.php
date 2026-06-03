<?php

namespace Domain\Membership\Team\Data\Resources\Roles;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RoleConfigData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        /** @var string[] */
        public array $permissions,
    ) {}

    public static function fromConfig(array $data): self
    {
        return new self(
            name: $data['name'],
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? [],
        );
    }
}
