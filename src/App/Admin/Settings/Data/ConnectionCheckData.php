<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Spatie\LaravelData\Data;

/**
 * Whether an AI provider accepted a key: connected, the key was refused,
 * or something else went wrong (the message says what). A warning is a
 * catch on a key that works, such as a free tier that reads only part of
 * each file.
 */
final class ConnectionCheckData extends Data
{
    public function __construct(
        public bool $connected,
        public bool $keyRefused = false,
        public ?string $message = null,
        public ?string $warning = null,
    ) {}
}
