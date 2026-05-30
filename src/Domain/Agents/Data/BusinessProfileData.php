<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;

class BusinessProfileData extends Data
{
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $website = null,
        public ?string $address = null,
        public ?string $opening_hours = null,
    ) {}
}
