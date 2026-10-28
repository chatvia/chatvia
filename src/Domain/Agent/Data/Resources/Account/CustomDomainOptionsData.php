<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\Account;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Whether the Deploy page may offer a custom domain, and what its CNAME
 * record points to. `is_licensed` and `is_enabled` are apart, so the page can
 * say which one is missing: a license key, or the admin's switch.
 */
#[TypeScript]
class CustomDomainOptionsData extends Data
{
    public function __construct(
        public bool $is_licensed,
        public bool $is_enabled,
        public string $cname_target,
    ) {}
}
