<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * The answer to activating a license key: the key activated for this
 * install's domain, or a message that says why it was not.
 */
final class LicenseActivationData extends Data
{
    public function __construct(
        public ?string $key = null,
        public ?string $message = null,
    ) {}
}
