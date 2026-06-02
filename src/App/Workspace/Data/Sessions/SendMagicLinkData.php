<?php

namespace App\Workspace\Data\Sessions;

use Spatie\LaravelData\Data;

class SendMagicLinkData extends Data
{
    public function __construct(
        public string $email,
    ) {}
}
