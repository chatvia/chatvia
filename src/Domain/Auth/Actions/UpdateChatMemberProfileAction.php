<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Data\UpdateChatMemberProfileData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateChatMemberProfileAction
{
    /**
     * @throws ValidationException
     */
    public function execute(UpdateChatMemberProfileData $data): void
    {
        $user = $data->user;

        if ($data->currentPassword) {
            $valid = Auth::guard('web')->validate([
                'email' => $user->email,
                'password' => $data->currentPassword,
            ]);

            if (! $valid) {
                throw ValidationException::withMessages([
                    'current_password' => __('auth.failed'),
                ]);
            }
        }

        $values = [];

        if ($data->name !== null) {
            $values['name'] = $data->name;
        }

        if ($data->newPassword) {
            $values['password'] = Hash::make($data->newPassword);
        }

        if ($values) {
            $user->update($values);
        }
    }
}
