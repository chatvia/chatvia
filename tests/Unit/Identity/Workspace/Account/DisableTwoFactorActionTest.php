<?php

use App\Identity\Workspace\Actions\Account\DisableTwoFactorAction;
use App\Identity\Workspace\Models\User;

it('turns off the dashboard and the admin panel two-factor sign-in', function (): void {
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => encrypt('dashboard-secret'),
        'two_factor_recovery_codes' => encrypt('["code"]'),
        'two_factor_confirmed_at' => now(),
        'app_authentication_secret' => 'panel-secret',
        'app_authentication_recovery_codes' => ['panel-code'],
    ])->save();

    app(DisableTwoFactorAction::class)->execute($user);

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->app_authentication_secret)->toBeNull()
        ->and($user->app_authentication_recovery_codes)->toBeNull();
});
