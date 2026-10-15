<?php

declare(strict_types=1);

namespace App\Admin\Status\Data;

use Spatie\LaravelData\Data;

/**
 * One line of the Status page's environment report: a name, its value, and
 * a tone when the value deserves a colour (a cache that is on, debug mode
 * left on in production).
 */
class EnvironmentFactData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $tone = null,
    ) {}
}
