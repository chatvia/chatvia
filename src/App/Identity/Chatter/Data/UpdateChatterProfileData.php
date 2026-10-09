<?php

namespace App\Identity\Chatter\Data;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Inputs for a chatter's own profile edit; every field is optional.
 */
class UpdateChatterProfileData extends Data
{
    public function __construct(
        public User $user,
        public ?string $name = null,
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
        public ?UploadedFile $photo = null,
    ) {}
}
