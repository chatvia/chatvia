<?php

namespace App\Data\Resources\Team;

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

    public static function fromConfig(array $data, string $code): self
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
