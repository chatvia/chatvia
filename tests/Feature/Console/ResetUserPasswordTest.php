<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * `admin:reset-password` lets the operator of a self-hosted install back into
 * an account when there is no e-mail for "Forgot password", or the
 * authenticator app is lost.
 */
it('sets a new password, matching the email in any case', function (): void {
    $user = User::factory()->create(['email' => 'owner@self-hosted.test']);

    $this->artisan('admin:reset-password', [
        'email' => 'Owner@Self-Hosted.test',
        'password' => 'a-new-long-password',
    ])->assertSuccessful();

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue();
});

it('fails for an email without an account', function (): void {
    $this->artisan('admin:reset-password', [
        'email' => 'nobody@self-hosted.test',
        'password' => 'a-new-long-password',
    ])->assertFailed();
});

it('keeps two-factor sign-in unless asked to turn it off', function (): void {
    $kept = User::factory()->create();
    $cleared = User::factory()->create();
    foreach ([$kept, $cleared] as $user) {
        $user->forceFill(['app_authentication_secret' => 'panel-secret'])->save();
    }

    $this->artisan('admin:reset-password', [
        'email' => $kept->email,
        'password' => 'a-new-long-password',
    ])->assertSuccessful();
    $this->artisan('admin:reset-password', [
        'email' => $cleared->email,
        'password' => 'a-new-long-password',
        '--disable-two-factor' => true,
    ])->assertSuccessful();

    expect($kept->fresh()->app_authentication_secret)->not->toBeNull()
        ->and($cleared->fresh()->app_authentication_secret)->toBeNull();
});
