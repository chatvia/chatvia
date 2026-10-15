<?php

use App\Identity\Workspace\Actions\Account\RemoveProfilePhotoAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Storage;

it('deletes the stored photo and clears it on the account', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    Storage::disk(User::PROFILE_IMAGE_DISK)->put('users/x/profile-photos/me.jpg', 'jpeg');
    $user = User::factory()->create(['profile_image_path' => 'users/x/profile-photos/me.jpg']);

    app(RemoveProfilePhotoAction::class)->execute($user);

    expect($user->fresh()->profile_image_path)->toBeNull();
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertMissing('users/x/profile-photos/me.jpg');
});

it('does nothing for an account without a photo', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    $user = User::factory()->create(['profile_image_path' => null]);

    expect(app(RemoveProfilePhotoAction::class)->execute($user)->profile_image_path)->toBeNull();
});
