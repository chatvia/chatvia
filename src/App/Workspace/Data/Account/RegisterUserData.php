<?php

namespace App\Workspace\Data\Account;

use Spatie\LaravelData\Data;

class RegisterUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $domain,
        public string $registered_ip,
    ) {}
}
