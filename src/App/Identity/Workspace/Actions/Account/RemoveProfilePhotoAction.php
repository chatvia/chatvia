<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the user's profile photo from storage and clears it on the account.
 */
class RemoveProfilePhotoAction
{
    public function execute(User $user): User
    {
        if ($user->profile_image_path) {
            Storage::disk(User::PROFILE_IMAGE_DISK)->delete($user->profile_image_path);

            $user->profile_image_path = null;
            $user->save();
        }

        return $user;
    }
}
