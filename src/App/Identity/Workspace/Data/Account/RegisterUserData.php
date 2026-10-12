<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use Spatie\LaravelData\Data;

/**
 * Inputs for the self-serve sign-up.
 */
class RegisterUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $registered_ip,
        // Lower-case ISO 3166-1 alpha-2 code of the visitor's country, read
        // from the request (Cloudflare header first). Null when unknown.
        public ?string $registered_country_code = null,
    ) {}
}
