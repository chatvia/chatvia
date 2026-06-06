<?php

use App\Identity\Chatter\Actions\UpdateChatterProfileAction;
use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('updates the chatter name when no password change is requested', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        name: 'New Name',
    ));

    expect($user->fresh()->name)->toBe('New Name');
});

it('updates the password when the current password matches', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current-pass'),
    ]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        currentPassword: 'current-pass',
        newPassword: 'brand-new-pass',
    ));

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('throws ValidationException when the current password is wrong', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('current-pass'),
    ]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        currentPassword: 'wrong-pass',
        newPassword: 'brand-new-pass',
    ));
})->throws(ValidationException::class);

it('leaves the user untouched when no values are supplied', function (): void {
    $user = User::factory()->create(['name' => 'Stable']);
    $originalUpdatedAt = $user->updated_at;

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
    ));

    expect($user->fresh()->name)->toBe('Stable')
        ->and($user->fresh()->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

it('stores the uploaded photo and sets the profile image path', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);

    $user = User::factory()->create(['profile_image_path' => null]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        photo: UploadedFile::fake()->image('avatar.png', 1200, 800),
    ));

    $path = $user->fresh()->profile_image_path;

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('profile-photos/')
        ->and($path)->toEndWith('.jpg');

    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($path);

    // The oversized upload is cropped to a centered 200x200 square, never stored full size.
    [$width, $height] = getimagesizefromstring(Storage::disk(User::PROFILE_IMAGE_DISK)->get($path));
    expect($width)->toBe(200)->and($height)->toBe(200);
});

it('replaces the previous photo when a new one is uploaded', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    Storage::disk(User::PROFILE_IMAGE_DISK)->put('profile-photos/old.jpg', 'old');

    $user = User::factory()->create(['profile_image_path' => 'profile-photos/old.jpg']);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        photo: UploadedFile::fake()->image('avatar.png', 600, 600),
    ));

    Storage::disk(User::PROFILE_IMAGE_DISK)->assertMissing('profile-photos/old.jpg');
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($user->fresh()->profile_image_path);
});

it('does not change the password when newPassword is not provided even with currentPassword check', function (): void {
    $user = User::factory()->create([
        'name' => 'Old',
        'password' => Hash::make('current-pass'),
    ]);
    $originalHash = $user->password;

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $user,
        name: 'New',
        currentPassword: 'current-pass',
    ));

    expect($user->fresh()->name)->toBe('New')
        ->and($user->fresh()->password)->toBe($originalHash);
});
