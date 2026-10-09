<?php

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for a password change.
 */
class UpdatePasswordData extends Data
{
    public function __construct(
        public User $user,
        public string $password,
    ) {}
}
