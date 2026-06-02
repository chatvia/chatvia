<?php

namespace App\Team\Data\Resources\Permissions;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class PermissionMetaData extends Data
{
    public function __construct(
        public string $label,
        public string $description,
        /** @var string[] */
        public array $requires = [],
    ) {}

    /**
     * @param  array<string, mixed>|string  $data
     */
    public static function fromConfig(array|string $data, string $code): self
    {
        if (! is_array($data)) {
            return new self(
                label: $data,
                description: '',
                requires: [],
            );
        }

        return new self(
            label: $data['label'] ?? $code,
            description: $data['description'] ?? '',
            requires: $data['requires'] ?? [],
        );
    }
}
