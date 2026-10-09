<?php

namespace Domain\Membership\Team\Data\Resources\Permissions;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A card of related permissions on the role editor (e.g. "Chat", "Sources"),
 * built from config('team-permissions.groups').
 */
#[TypeScript]
class PermissionGroupData extends Data
{
    public function __construct(
        public string $scope,
        public string $label,
        public ?string $description,
        /** @var string[] */
        public array $permissions,
    ) {}

    public static function fromConfig(array $data): self
    {
        return new self(
            scope: $data['scope'] ?? 'agent',
            label: $data['label'],
            description: $data['description'] ?? null,
            permissions: $data['permissions'] ?? [],
        );
    }
}
