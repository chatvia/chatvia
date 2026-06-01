<?php

use App\Auth\Models\User;
use App\Auth\Actions\Account\DeleteUserAccountAction;

it('hard-deletes the user row', function (): void {
    $user = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

it('does not touch unrelated users', function (): void {
    $target = User::factory()->create();
    $bystander = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($target);

    expect(User::whereKey($bystander->id)->exists())->toBeTrue();
});
