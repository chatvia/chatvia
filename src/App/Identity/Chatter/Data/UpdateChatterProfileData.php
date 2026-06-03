<?php

namespace App\Identity\Chatter\Data;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class UpdateChatterProfileData extends Data
{
    public function __construct(
        public User $user,
        public ?string $name = null,
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
    ) {}
}
