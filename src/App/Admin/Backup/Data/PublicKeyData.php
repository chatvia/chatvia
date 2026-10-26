<?php

declare(strict_types=1);

namespace App\Admin\Backup\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * What an OpenPGP public key says about itself: its fingerprint, the first
 * name and e-mail on it, and when it stops working (null: never). Shown on
 * the Backups settings so the admin can check it is their key.
 */
class PublicKeyData extends Data
{
    public function __construct(
        public string $fingerprint,
        public ?string $userId,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
