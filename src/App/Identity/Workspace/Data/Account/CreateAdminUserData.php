<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use Spatie\LaravelData\Data;

/**
 * Inputs for {@see CreateAdminUserAction}: the name, email and plain-text
 * password of a new platform admin. The action hashes the password; this object
 * never leaves the console command that builds it.
 */
class CreateAdminUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
