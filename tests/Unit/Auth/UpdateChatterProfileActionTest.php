<?php

use App\Auth\Models\User;
use Domain\Auth\Actions\UpdateChatterProfileAction;
use Domain\Auth\Data\UpdateChatterProfileData;
use Illuminate\Support\Facades\Hash;
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
