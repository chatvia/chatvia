<?php

declare(strict_types=1);

namespace App\Admin\Issue\Data;

use Spatie\LaravelData\Data;

/** A button on an issue that leads to where it is fixed: a settings page, or a page at the service. */
class IssueActionData extends Data
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $external = false,
    ) {}
}
