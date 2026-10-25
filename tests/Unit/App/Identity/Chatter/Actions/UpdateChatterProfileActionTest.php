<?php

/**
 * UpdateChatterProfileAction: a chatter edits their own name, password (the
 * current one is checked against this chatter's own hash) and photo (stored
 * under `chatters/{external_id}/profile-photos/`).
 */

use App\Identity\Chatter\Actions\UpdateChatterProfileAction;
use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('updates the chatter name when no password change is requested', function (): void {
    $chatter = Chatter::factory()->create(['name' => 'Old Name']);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        name: 'New Name',
    ));

    expect($chatter->fresh()->name)->toBe('New Name');
});

it('updates the password when the current password matches and rotates the remember-me token', function (): void {
    $chatter = Chatter::factory()->create([
        'password' => Hash::make('current-pass'),
        'remember_token' => 'old-remember-token',
    ]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        currentPassword: 'current-pass',
        newPassword: 'brand-new-pass',
    ));

    expect(Hash::check('brand-new-pass', $chatter->fresh()->password))->toBeTrue()
        ->and($chatter->fresh()->remember_token)->not->toBe('old-remember-token');
});

it('throws ValidationException when the current password is wrong', function (): void {
    $chatter = Chatter::factory()->create([
        'password' => Hash::make('current-pass'),
    ]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        currentPassword: 'wrong-pass',
        newPassword: 'brand-new-pass',
    ));
})->throws(ValidationException::class);

it('checks the current password against this chatter only, not another account with the same email', function (string $foreignPassword): void {
    $workspace = User::factory()->workspace()->create();
    // A staff account and a chatter of another workspace share the email and
    // know a different password; neither may unlock this chatter's change.
    User::factory()->employee($workspace)->create([
        'email' => 'same@example.test',
        'password' => Hash::make('staff-pass'),
    ]);
    Chatter::factory()->create(['email' => 'same@example.test', 'password' => Hash::make('other-pass')]);
    $chatter = Chatter::factory()->ofWorkspace($workspace)->create([
        'email' => 'same@example.test',
        'password' => Hash::make('current-pass'),
    ]);

    expect(fn () => app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        currentPassword: $foreignPassword,
        newPassword: 'brand-new-pass',
    )))->toThrow(ValidationException::class)
        ->and(Hash::check('current-pass', $chatter->fresh()->password))->toBeTrue();
})->with([
    'the staff account\'s password' => 'staff-pass',
    'the other workspace chatter\'s password' => 'other-pass',
]);

it('does not save the chatter when no values are supplied', function (): void {
    $chatter = Chatter::factory()->create(['name' => 'Stable']);
    $saves = 0;
    Chatter::saving(function () use (&$saves): void {
        $saves++;
    });

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
    ));

    expect($saves)->toBe(0)
        ->and($chatter->fresh()->name)->toBe('Stable');
});

it('stores the uploaded photo under the chatter folder and sets the profile image path', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);

    $chatter = Chatter::factory()->create(['profile_image_path' => null]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        photo: UploadedFile::fake()->image('avatar.png', 1200, 800),
    ));

    $path = $chatter->fresh()->profile_image_path;

    expect($path)->not->toBeNull()
        ->and($path)->toStartWith('chatters/'.$chatter->external_id.'/profile-photos/')
        ->and($path)->toEndWith('.jpg');

    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($path);

    // The oversized upload is cropped to a centered 200x200 square, never stored full size.
    [$width, $height] = getimagesizefromstring(Storage::disk(User::PROFILE_IMAGE_DISK)->get($path));
    expect($width)->toBe(200)->and($height)->toBe(200);
});

it('replaces the previous photo when a new one is uploaded', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);

    $chatter = Chatter::factory()->create();
    $oldPath = 'chatters/'.$chatter->external_id.'/profile-photos/old.jpg';
    Storage::disk(User::PROFILE_IMAGE_DISK)->put($oldPath, 'old');
    $chatter->update(['profile_image_path' => $oldPath]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        photo: UploadedFile::fake()->image('avatar.png', 600, 600),
    ));

    Storage::disk(User::PROFILE_IMAGE_DISK)->assertMissing($oldPath);
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertExists($chatter->fresh()->profile_image_path);
});

it('does not change the password when newPassword is not provided even with currentPassword check', function (): void {
    $chatter = Chatter::factory()->create([
        'name' => 'Old',
        'password' => Hash::make('current-pass'),
    ]);
    $originalHash = $chatter->password;

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        name: 'New',
        currentPassword: 'current-pass',
    ));

    expect($chatter->fresh()->name)->toBe('New')
        ->and($chatter->fresh()->password)->toBe($originalHash);
});
