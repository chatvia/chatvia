<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

/**
 * Inputs for a profile edit.
 */
class UpdateProfileData extends Data
{
    public function __construct(
        public User $user,
        public string $name,
        public string $email,
        public ?UploadedFile $photo = null,
    ) {}
}
