<?php

namespace Domain\Auth\Data;

use App\Auth\Models\User;
use Spatie\LaravelData\Data;

class UpdateChatMemberProfileData extends Data
{
    public function __construct(
        public User $user,
        public ?string $name = null,
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
    ) {}
}
