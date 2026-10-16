<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Data;

use App\Identity\Chatter\Models\Chatter;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Inputs for a chatter's own profile edit; a field left out arrives as
 * `Optional` (or null) and is left alone.
 */
class UpdateChatterProfileData extends Data
{
    public function __construct(
        public Chatter $user,
        public string|Optional $name = new Optional,
        public ?string $currentPassword = null,
        public ?string $newPassword = null,
        public ?UploadedFile $photo = null,
    ) {}
}
