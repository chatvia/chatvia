<?php

namespace App\Identity\ApiKey\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class CreateApiKeyData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
    ) {}
}
