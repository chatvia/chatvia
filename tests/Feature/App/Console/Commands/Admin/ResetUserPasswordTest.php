<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * `chatvia:admin:reset-password` lets the operator of a self-hosted install
 * back into an account when there is no e-mail for "Forgot password": it
 * sets the new password, keeps two-factor sign-in unless asked, and fails
 * for an email without an account.
 */
it('sets a new password, matching the email in any case, and keeps two-factor sign-in', function (): void {
    $user = User::factory()->create(['email' => 'owner@self-hosted.test']);
    $user->forceFill(['app_authentication_secret' => 'panel-secret'])->save();

    $this->artisan('chatvia:admin:reset-password', [
        'email' => 'Owner@Self-Hosted.test',
        'password' => 'a-new-long-password',
    ])->assertSuccessful();

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->app_authentication_secret)->toBe('panel-secret');
});

it('fails for an email without an account', function (): void {
    $this->artisan('chatvia:admin:reset-password', [
        'email' => 'nobody@self-hosted.test',
        'password' => 'a-new-long-password',
    ])->assertFailed();
});
