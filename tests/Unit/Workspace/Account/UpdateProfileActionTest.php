<?php

use App\Identity\Workspace\Actions\Account\UpdateProfileAction;
use App\Identity\Workspace\Data\Account\UpdateProfileData;
use App\Identity\Workspace\Models\User;

it('updates name and email and returns the user', function (): void {
    $user = User::factory()->create([
        'name' => 'Old',
        'email' => 'old@example.test',
    ]);

    $result = app(UpdateProfileAction::class)->execute(new UpdateProfileData(
        user: $user,
        name: 'New',
        email: 'old@example.test',
    ));

    expect($result)->toBeInstanceOf(User::class)
        ->and($result->name)->toBe('New')
        ->and($user->fresh()->name)->toBe('New');
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

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
