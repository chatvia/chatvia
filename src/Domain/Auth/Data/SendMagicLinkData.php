<?php

namespace Domain\Auth\Data;

use Spatie\LaravelData\Data;

class SendMagicLinkData extends Data
{
    public function __construct(
        public string $email,
    ) {}
}
