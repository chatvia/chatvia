<?php

use App\Identity\Chatter\Actions\UpdateChatterProfileAction;
use App\Identity\Chatter\Data\UpdateChatterProfileData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Support\Exceptions\DomainValidationException;

/**
 * UpdateChatterProfileAction: a chatter edits their own name, password (the
 * current one is checked against this chatter's own hash) and photo (stored
 * under `workspaces/{ws}/chatters/{external_id}/profile-photos/`).
 */
it('changes the name and the password when the current one matches, and rotates the remember-me token', function (): void {
    $chatter = Chatter::factory()->create([
        'name' => 'Old Name',
        'password' => Hash::make('current-pass'),
        'remember_token' => 'old-remember-token',
    ]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        name: 'New Name',
        currentPassword: 'current-pass',
        newPassword: 'brand-new-pass',
    ));

    $chatter = $chatter->fresh();
    expect($chatter->name)->toBe('New Name')
        ->and(Hash::check('brand-new-pass', $chatter->password))->toBeTrue()
        ->and($chatter->remember_token)->not->toBe('old-remember-token');
});

it('refuses a wrong current password, even another account\'s with the same email', function (string $wrongPassword): void {
    $workspace = User::factory()->workspace()->create();
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
        currentPassword: $wrongPassword,
        newPassword: 'brand-new-pass',
    )))->toThrow(DomainValidationException::class)
        ->and(Hash::check('current-pass', $chatter->fresh()->password))->toBeTrue();
})->with([
    'the staff account\'s password' => 'staff-pass',
    'the other workspace chatter\'s password' => 'other-pass',
]);

it('stores a new photo cropped under the chatter folder and removes the previous one', function (): void {
    Storage::fake(User::PROFILE_IMAGE_DISK);
    $chatter = Chatter::factory()->create();
    $oldPath = $chatter->storageRoot().'/profile-photos/old.jpg';
    Storage::disk(User::PROFILE_IMAGE_DISK)->put($oldPath, 'old');
    $chatter->update(['profile_image_path' => $oldPath]);

    app(UpdateChatterProfileAction::class)->execute(new UpdateChatterProfileData(
        user: $chatter,
        photo: UploadedFile::fake()->image('avatar.png', 1200, 800),
    ));

    $path = $chatter->fresh()->profile_image_path;
    [$width, $height] = getimagesizefromstring(Storage::disk(User::PROFILE_IMAGE_DISK)->get($path));
    expect($path)->toStartWith('workspaces/'.$chatter->workspace->external_id.'/chatters/'.$chatter->external_id.'/profile-photos/')
        ->toEndWith('.jpg')
        ->and($width)->toBe(200)
        ->and($height)->toBe(200);
    Storage::disk(User::PROFILE_IMAGE_DISK)->assertMissing($oldPath);
});
