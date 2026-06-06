<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\ProfilePhotoStorage;

class UpdateProfileAction
{
    public function __construct(
        private readonly ProfilePhotoStorage $photos,
    ) {}

    public function execute(UpdateProfileData $data): User
    {
        $user = $data->user;

        $user->fill([
            'name' => $data->name,
            'email' => $data->email,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($data->photo) {
            $user->profile_image_path = $this->photos->store($user, $data->photo);
        }

        $user->save();

        return $user;
    }
}
