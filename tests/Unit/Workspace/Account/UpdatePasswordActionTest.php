<?php

use App\Workspace\Models\User;
use App\Workspace\Actions\Account\UpdatePasswordAction;
use App\Workspace\Data\Account\UpdatePasswordData;
use Illuminate\Support\Facades\Hash;

it('hashes and stores the new password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    app(UpdatePasswordAction::class)->execute(new UpdatePasswordData(
        user: $user,
        password: 'new-password',
    ));

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
});

it('does not store the new password in plain text', function (): void {
    $user = User::factory()->create();

    app(UpdatePasswordAction::class)->execute(new UpdatePasswordData(
        user: $user,
        password: 'plain-text-secret',
    ));

    expect($user->fresh()->password)->not->toBe('plain-text-secret');
});
