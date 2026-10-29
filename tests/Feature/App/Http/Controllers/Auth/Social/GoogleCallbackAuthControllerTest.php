<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * The Google sign-in callback (GoogleCallbackAuthController): a linked or
 * verified same-email identity signs in, two-factor accounts go to the
 * challenge, and an unverified email (an account-takeover attempt) or a
 * deactivated account is sent back to login. Which account matches, and that
 * an unknown identity creates none, is SocialLoginAction's rule
 * (SocialLoginActionTest).
 */

/**
 * Stub Socialite's Google driver so the callback resolves the given external
 * identity without any real OAuth round-trip. `$raw` carries provider claims
 * such as `email_verified`.
 *
 * @param  array<string, mixed>  $raw
 */
function gcaFakeGoogleUser(string $id, ?string $email, array $raw = []): void
{
    $socialUser = (new SocialiteUser)
        ->map(['id' => $id, 'email' => $email])
        ->setRaw($raw);

    $provider = Mockery::mock(AbstractProvider::class);
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('signs in a linked identity or a verified same-email one', function (Closure $identity): void {
    $user = User::factory()->create(['email' => 'owner@example.com']);
    $identity($user);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
})->with([
    'linked' => [function (User $user): void {
        $user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'google-123']);
        gcaFakeGoogleUser('google-123', $user->email, ['email_verified' => true]);
    }],
    'verified same email' => [fn (User $user) => gcaFakeGoogleUser('google-456', 'owner@example.com', ['verified_email' => true])],
]);

it('sends an unverified same-email identity or a deactivated account back to login', function (Closure $identity): void {
    $user = User::factory()->create(['email' => 'victim@example.com']);
    $identity($user);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBeNull();
})->with([
    'unverified email' => [fn (User $user) => gcaFakeGoogleUser('google-evil', 'victim@example.com', ['email_verified' => false])],
    'deactivated account' => [function (User $user): void {
        $user->forceFill(['deactivated_at' => now()])->save();
        $user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'google-off']);
        gcaFakeGoogleUser('google-off', $user->email, ['email_verified' => true]);
    }],
]);

it('routes a two-factor-enabled user to the challenge instead of signing them in', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    $user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'google-2fa']);
    gcaFakeGoogleUser('google-2fa', $user->email, ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->getAuthIdentifier());

    expect(auth()->check())->toBeFalse();
});
