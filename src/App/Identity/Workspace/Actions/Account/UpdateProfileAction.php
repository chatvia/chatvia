<?php

namespace App\Identity\Workspace\Actions\Account;

use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UpdateProfileAction
{
    private const PROFILE_IMAGE_SIZE = 512;

    private const PROFILE_IMAGE_QUALITY = 85;

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
            $user->profile_image_path = $this->storePhoto($user, $data->photo);
        }

        $user->save();

        return $user;
    }

    private function storePhoto(User $user, UploadedFile $photo): string
    {
        $disk = Storage::disk(User::PROFILE_IMAGE_DISK);

        if ($user->profile_image_path) {
            $disk->delete($user->profile_image_path);
        }

        $encoded = Image::read($photo->getRealPath())
            ->cover(self::PROFILE_IMAGE_SIZE, self::PROFILE_IMAGE_SIZE)
            ->toJpeg(self::PROFILE_IMAGE_QUALITY);

        $filepath = 'profile-photos/'.Str::uuid().'.jpg';

        $disk->put($filepath, (string) $encoded, ['visibility' => 'public']);

        return $filepath;
    }
}
