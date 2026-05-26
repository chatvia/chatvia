<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Domain\Auth\Data\UpdateProfileData;

class UpdateProfileAction
{
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

        $user->save();

        return $user;
    }
}
