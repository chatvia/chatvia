<?php

use App\Identity\Workspace\Actions\Account\UpdatePasswordAction;
use App\Identity\Workspace\Data\Account\UpdatePasswordData;
use App\Identity\Workspace\Models\User;
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
