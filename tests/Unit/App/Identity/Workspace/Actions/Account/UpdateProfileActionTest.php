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
it('updates name and email and returns the user', function (): void {
    $user = User::factory()->create([
        'name' => 'Old',
        'email' => 'old@example.test',
    ]);

    $result = app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: 'New',
        email: 'new@example.test',
    ));

    expect($result->name)->toBe('New')
        ->and($result->email)->toBe('new@example.test')
        ->and($user->fresh()->only(['name', 'email']))->toBe(['name' => 'New', 'email' => 'new@example.test']);
});

it('stores a new photo under the user own folder', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    $user = User::factory()->create();

    app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: $user->name,
        email: $user->email,
        photo: UploadedFile::fake()->image('me.png', 400, 300),
    ));

    $path = $user->fresh()->profile_image_path;

    expect($path)->toStartWith('workspaces/'.$user->workspace()->external_id.'/members/'.$user->external_id.'/profile-photos/')
        ->and($path)->toEndWith('.jpg');
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($path);
});

it('clears email_verified_at when the email changes', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.test',
        'email_verified_at' => now(),
    ]);

    app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: $user->name,
        email: 'new@example.test',
    ));

    expect($user->fresh()->email)->toBe('new@example.test')
        ->and($user->fresh()->email_verified_at)->toBeNull();
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
