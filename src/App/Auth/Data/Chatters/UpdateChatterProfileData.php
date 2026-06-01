<?php

namespace App\Auth\Data\Chatters;

use App\Auth\Models\User;
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
