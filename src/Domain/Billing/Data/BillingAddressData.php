<?php

namespace Domain\Billing\Data;

use Spatie\LaravelData\Data;

class BillingAddressData extends Data
{
    public function __construct(
        public string $line1,
        public ?string $line2,
        public string $city,
        public string $state,
        public string $postal_code,
        public string $country,
    ) {}
}
