<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Sessions;

use Spatie\LaravelData\Data;

/**
 * What one sign-in told about the device: whether it was new for the account,
 * and whether the account already knew other devices then.
 */
class DeviceSightingData extends Data
{
    public function __construct(
        public bool $isNew,
        public bool $hasOtherDevices,
    ) {}

    /**
     * A new device on an account that already had one. The first device ever
     * is the only one, so it is no news.
     */
    public function isUnfamiliar(): bool
    {
        return $this->isNew && $this->hasOtherDevices;
    }
}
