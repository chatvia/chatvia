<?php

namespace App\Identity\Workspace\Data\Sessions;

use Spatie\LaravelData\Data;

/**
 * Email-and-password credentials for the hosted-chat login.
 */
class LoginData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}
}
