<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * chatvia.ai's answer to the daily license check: whether the key is still
 * active for this domain (null when it could not say) and, while it is, the
 * key signed afresh, which carries a renewed key's new last day.
 */
final class LicenseCheckData extends Data
{
    public function __construct(
        public ?bool $active = null,
        public ?string $key = null,
    ) {}
}
