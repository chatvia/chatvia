<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

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
