<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateChatterProfileAction
{
    public function __construct(
        private readonly PasswordAuthenticator $authenticator,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(UpdateChatterProfileData $data): void
    {
        $user = $data->user;

        if ($data->currentPassword) {
            if (! $this->authenticator->validateCredentials($user->email, $data->currentPassword)) {
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
