<?php

namespace App\Identity\Workspace\Data\Sessions;

use Spatie\LaravelData\Data;

/**
 * Inputs for sending a magic sign-in link.
 */
class SendMagicLinkData extends Data
{
    public function __construct(
        public string $email,
    ) {}
}
