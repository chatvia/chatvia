<?php

namespace App\Workspace\Actions\Account;

use App\Workspace\Data\Account\UpdateProfileData;
use App\Workspace\Models\User;

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
