<?php

namespace App\Marketing\Data;

use Spatie\LaravelData\Data;

/**
 * A message sent through the public contact form.
 */
class ContactMessageData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $company,
        public string $message,
    ) {}
}
