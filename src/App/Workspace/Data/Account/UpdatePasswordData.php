<?php

namespace App\Workspace\Data\Account;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

class UpdatePasswordData extends Data
{
    public function __construct(
        public User $user,
        public string $password,
    ) {}
}
