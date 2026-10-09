<?php

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use Spatie\LaravelData\Data;

/**
 * Inputs for {@see CreateAdminUserAction}.
 */
class CreateAdminUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
