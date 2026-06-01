<?php

namespace App\Auth\Actions\Account;

use App\Auth\Data\Account\UpdateProfileData;
use App\Auth\Models\User;

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
