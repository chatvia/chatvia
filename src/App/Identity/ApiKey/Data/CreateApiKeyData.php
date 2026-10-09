<?php

declare(strict_types=1);

namespace App\Identity\ApiKey\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for creating an API key.
 */
class CreateApiKeyData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
    ) {}
}
