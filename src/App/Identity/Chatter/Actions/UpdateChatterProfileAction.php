<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Workspace\Services\PasswordAuthenticator;
use App\Identity\Workspace\Services\ProfilePhotoStorage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A chatter edits their own profile on the hosted chat: name, password (the
 * current one is checked first) and photo.
 */
class UpdateChatterProfileAction
{
    public function __construct(
        private readonly PasswordAuthenticator $authenticator,
        private readonly ProfilePhotoStorage $photos,
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

        if ($data->name !== null) {
            $user->name = $data->name;
        }

        if ($data->newPassword) {
            $user->password = Hash::make($data->newPassword);
        }

        if ($data->photo) {
            $user->profile_image_path = $this->photos->store($user, $data->photo);
        }

        if ($user->isDirty()) {
            $user->save();
        }
    }
}
