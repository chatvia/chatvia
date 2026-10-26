<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * `chatvia:admin:reset-password` lets the operator of a self-hosted install back into
 * an account when there is no e-mail for "Forgot password", or the
 * authenticator app is lost.
 */
it('sets a new password, matching the email in any case', function (): void {
    $user = User::factory()->create(['email' => 'owner@self-hosted.test']);

    $this->artisan('chatvia:admin:reset-password', [
        'email' => 'Owner@Self-Hosted.test',
        'password' => 'a-new-long-password',
    ])->assertSuccessful();

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue();
});

it('asks for the email and password when run with no arguments', function (): void {
    $user = User::factory()->create(['email' => 'owner@self-hosted.test']);

    $this->artisan('chatvia:admin:reset-password')
        ->expectsQuestion('Email address of the account', 'owner@self-hosted.test')
        ->expectsQuestion('New password', 'a-new-long-password')
        ->assertSuccessful();

    expect(Hash::check('a-new-long-password', $user->fresh()->password))->toBeTrue();
});

it('asks whether to turn off two-factor sign-in when it asked for the email', function (bool $answer): void {
    $user = User::factory()->create();
    $user->forceFill(['app_authentication_secret' => 'panel-secret'])->save();

    $this->artisan('chatvia:admin:reset-password', ['password' => 'a-new-long-password'])
        ->expectsQuestion('Email address of the account', $user->email)
        ->expectsConfirmation('This account uses two-factor sign-in. Turn it off too?', $answer ? 'yes' : 'no')
        ->assertSuccessful();

    expect($user->fresh()->app_authentication_secret)->toBe($answer ? null : 'panel-secret');
})->with([
    'turned off' => [true],
    'kept' => [false],
]);

it('fails for an email without an account', function (): void {
    $this->artisan('chatvia:admin:reset-password', [
        'email' => 'nobody@self-hosted.test',
        'password' => 'a-new-long-password',
    ])->assertFailed();
});

it('keeps two-factor sign-in unless asked to turn it off', function (array $options, bool $kept): void {
    $user = User::factory()->create();
    $user->forceFill(['app_authentication_secret' => 'panel-secret'])->save();

    $this->artisan('chatvia:admin:reset-password', [
        'email' => $user->email,
        'password' => 'a-new-long-password',
        ...$options,
    ])->assertSuccessful();

    expect($user->fresh()->app_authentication_secret)->toBe($kept ? 'panel-secret' : null);
})->with([
    'without the option' => [[], true],
    'with --disable-two-factor' => [['--disable-two-factor' => true], false],
]);
