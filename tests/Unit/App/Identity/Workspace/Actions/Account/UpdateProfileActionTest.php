<?php

use App\Identity\Workspace\Actions\Account\UpdateProfileAction;
use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Saving the profile: name and email, a new photo under the person's own
 * `workspaces/{ws}/members/{ext}/profile-photos/` folder, and a changed email
 * must be verified again.
 */
it('saves the name, email and a new photo under the person\'s own folder, and asks to verify the changed email', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    $user = User::factory()->create(['name' => 'Old', 'email' => 'old@example.test', 'email_verified_at' => now()]);

    $result = app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: 'New',
        email: 'new@example.test',
        photo: UploadedFile::fake()->image('me.png', 400, 300),
    ));

    $fresh = $user->fresh();
    expect($result->name)->toBe('New')
        ->and($fresh->only(['name', 'email']))->toBe(['name' => 'New', 'email' => 'new@example.test'])
        ->and($fresh->email_verified_at)->toBeNull()
        ->and($fresh->profile_image_path)->toStartWith('workspaces/'.$user->workspace()->external_id.'/members/'.$user->external_id.'/profile-photos/')
        ->toEndWith('.jpg');
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($fresh->profile_image_path);
});

it('keeps email_verified_at when the email is unchanged', function (): void {
    $verifiedAt = now()->subDay()->startOfDay();
    $user = User::factory()->create([
        'email' => 'same@example.test',
        'email_verified_at' => $verifiedAt,
    ]);

    app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: 'Updated Name',
        email: 'same@example.test',
    ));

    expect($user->fresh()->email_verified_at?->toDateTimeString())->toBe($verifiedAt->toDateTimeString());
});
