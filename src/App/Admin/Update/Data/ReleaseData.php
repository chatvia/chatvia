<?php

declare(strict_types=1);

namespace App\Admin\Update\Data;

use Spatie\LaravelData\Data;

/**
 * A ChatVia.ai release: its version, like 1.5.0, and the page of its
 * release notes.
 */
final class ReleaseData extends Data
{
    public function __construct(
        public readonly string $version,
        public readonly string $url,
    ) {}
}
