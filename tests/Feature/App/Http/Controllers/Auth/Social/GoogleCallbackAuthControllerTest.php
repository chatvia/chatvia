<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * The Google sign-in callback (GoogleCallbackAuthController): a linked or
 * verified same-email identity signs in, two-factor accounts go to the
 * challenge, and an unknown identity, an unverified email or a deactivated
 * account is sent back to login. Which account matches is SocialLoginAction's
 * rule (SocialLoginActionTest).
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

it('logs in a user already linked to the social identity and redirects to the dashboard', function (): void {
    $user = User::factory()->create();
    $user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'google-123']);

    gcaFakeGoogleUser('google-123', $user->email, ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('auto-links a verified same-email identity to the existing account and logs in', function (): void {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    // Google's raw payload uses `verified_email`.
    gcaFakeGoogleUser('google-456', 'owner@example.com', ['verified_email' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('rejects an unknown social identity instead of creating an account', function (): void {
    gcaFakeGoogleUser('google-789', 'stranger@example.com', ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(auth()->check())->toBeFalse()
        ->and(User::query()->where('email', 'stranger@example.com')->exists())->toBeFalse();
});

it('refuses to auto-link when the provider says the email is unverified', function (): void {
    // Account-takeover defence: an attacker controlling an unverified
    // provider email must not be linked into the victim's account.
    User::factory()->create(['email' => 'victim@example.com']);

    gcaFakeGoogleUser('google-evil', 'victim@example.com', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(auth()->check())->toBeFalse();
});

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

it('refuses a deactivated account even with a linked social identity', function (): void {
    $user = User::factory()->create(['deactivated_at' => now()]);
    $user->socials()->create(['provider' => SocialProvider::Google, 'provider_id' => 'google-off']);

    gcaFakeGoogleUser('google-off', $user->email, ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBeNull();
});
