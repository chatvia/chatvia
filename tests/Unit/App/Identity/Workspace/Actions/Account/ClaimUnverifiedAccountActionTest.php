<?php

use App\Identity\Workspace\Actions\Account\ClaimUnverifiedAccountAction;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Facades\Hash;

/**
 * Claiming an account nobody verified drops every way in that whoever
 * registered it could have set up; a verified account is left alone.
 */
it('verifies the account and drops the password, social links, two-factor and API keys', function (): void {
    $user = User::factory()->unverified()->create([
        'password' => 'squatter-password',
        'remember_token' => 'old-remember-token',
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Github, 'provider_id' => 'gh-squatter']);
    $user->createToken('squatter key');

    app(ClaimUnverifiedAccountAction::class)->execute($user);

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('squatter-password', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe('old-remember-token')
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->socials()->exists())->toBeFalse()
        ->and($user->tokens()->exists())->toBeFalse();
});

it('leaves a verified account as it is', function (): void {
    $user = User::factory()->create(['password' => 'own-password']);
    UserSocial::query()->create(['user_id' => $user->id, 'provider' => SocialProvider::Github, 'provider_id' => 'gh-own']);

    app(ClaimUnverifiedAccountAction::class)->execute($user);

    expect(Hash::check('own-password', $user->fresh()->password))->toBeTrue()
        ->and($user->socials()->exists())->toBeTrue();
});
