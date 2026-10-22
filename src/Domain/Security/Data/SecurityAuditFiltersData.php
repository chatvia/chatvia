<?php

declare(strict_types=1);

namespace Domain\Security\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The active filters of the security audit viewer. A null field means the
 * page is not filtering on it.
 */
#[TypeScript]
class SecurityAuditFiltersData extends Data
{
    public function __construct(
        public ?string $category,
        public ?string $outcome,
    ) {}
}
