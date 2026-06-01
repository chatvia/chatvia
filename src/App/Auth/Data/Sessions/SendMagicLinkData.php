<?php

namespace App\Auth\Data\Sessions;

use Spatie\LaravelData\Data;

class SendMagicLinkData extends Data
{
    public function __construct(
        public string $email,
    ) {}
}
