<?php

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Stores profile photos: cropped to a small square, re-encoded as JPEG, on the
 * public assets disk. The original upload is never kept.
 */
class ProfilePhotoStorage
{
    // Avatars render small (≤96px); a 200×200 square is plenty for retina and
    // keeps the stored file tiny. We never persist the original upload.
    private const SIZE = 200;

    private const QUALITY = 82;

    /**
     * Crop to a centered square, downscale, re-encode, and store the photo,
     * replacing the user's existing one. Returns the stored path to persist
     * on the user.
     */
    public function store(User $user, UploadedFile $photo): string
    {
        $disk = Storage::disk(User::PROFILE_IMAGE_DISK);

        if ($user->profile_image_path) {
            $disk->delete($user->profile_image_path);
        }

        $encoded = Image::decodePath($photo->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->encode(new JpegEncoder(quality: self::QUALITY));

        $path = 'users/'.$user->external_id.'/profile-photos/'.Str::uuid().'.jpg';

        $disk->put($path, (string) $encoded, ['visibility' => 'public']);

        return $path;
    }
}
