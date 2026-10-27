<?php

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Workspace\Services\ProfilePhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;
use Support\Exceptions\DomainValidationException;

/**
 * A chatter edits their own profile on the hosted chat: name, password (the
 * current one is checked first) and photo.
 *
 * A new password ends the chatter's other chat sessions on their next request
 * (each keeps a hash of the password it signed in with: AuthenticateSession)
 * and rotates the remember-me token; this session stores the new hash and
 * stays signed in.
 */
class UpdateChatterProfileAction
{
    public function __construct(
        private readonly ProfilePhotoStorage $photos,
    ) {}

    /**
     * @throws DomainValidationException
     */
    public function execute(UpdateChatterProfileData $data): void
    {
        $user = $data->user;

        // Checked against this chatter's own hash, never by an email lookup
        // that could find a different account.
        if ($data->currentPassword) {
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw new DomainValidationException('current_password', __('auth.failed'));
            }
        }

        if (! $data->name instanceof Optional) {
            $user->name = $data->name;
        }

        if ($data->newPassword) {
            $user->password = Hash::make($data->newPassword);
            $user->setRememberToken(Str::random(60));
        }

        if ($data->photo instanceof UploadedFile) {
            $user->profile_image_path = $this->photos->store($user, $data->photo);
        }

        if ($user->isDirty()) {
            $user->save();
        }
    }
}
