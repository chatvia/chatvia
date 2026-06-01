<?php

namespace App\Auth\Data\Account;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class UpdatePasswordData extends Data
{
    public function __construct(
        public User $user,
        public string $password,
    ) {}
}
