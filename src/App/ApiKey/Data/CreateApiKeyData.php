<?php

namespace App\ApiKey\Data;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreateApiKeyData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
    ) {}
}
