<?php

namespace App\Marketing\Data;

use Spatie\LaravelData\Data;

/**
 * The customer (controller) details a visitor fills in to generate their own
 * Data Processing Agreement. Only the company name is required; a missing
 * detail leaves its line blank on the PDF.
 */
class DpaCustomerData extends Data
{
    public function __construct(
        public string $companyName,
        public ?string $registrationNumber = null,
        public ?string $registeredAddress = null,
        public ?string $country = null,
        public ?string $contactEmail = null,
        public ?string $signatoryName = null,
        public ?string $signatoryTitle = null,
    ) {}
}
