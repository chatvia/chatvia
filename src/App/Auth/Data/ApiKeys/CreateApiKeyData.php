<?php

namespace App\Auth\Data\ApiKeys;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class CreateApiKeyData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
    ) {}
}
