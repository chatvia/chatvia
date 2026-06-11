<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Support\Enums\User\SocialProvider;

/**
 * Stub Socialite's Google driver so the callback resolves the given external
 * identity without any real OAuth round-trip. `$raw` carries provider claims
 * such as `email_verified`.
 *
 * @param  array<string, mixed>  $raw
 */
function fakeGoogleSocialiteUser(string $id, ?string $email, array $raw = []): void
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
    UserSocial::create([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'google-123',
    ]);

    fakeGoogleSocialiteUser('google-123', $user->email, ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('auto-links a verified same-email identity to the existing account and logs in', function (): void {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    // Google's raw payload uses `verified_email`.
    fakeGoogleSocialiteUser('google-456', 'owner@example.com', ['verified_email' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);

    // The identity was persisted so the next sign-in takes the fast path.
    expect(
        UserSocial::query()
            ->where('user_id', $user->id)
            ->where('provider', SocialProvider::Google)
            ->where('provider_id', 'google-456')
            ->exists()
    )->toBeTrue();
});

it('rejects an unknown social identity instead of creating an account', function (): void {
    fakeGoogleSocialiteUser('google-789', 'stranger@example.com', ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(User::where('email', 'stranger@example.com')->exists())->toBeFalse();
});

it('refuses to auto-link when the provider says the email is unverified', function (): void {
    // Account-takeover defence: an attacker controlling an unverified
    // provider email must not be linked into the victim's account.
    $victim = User::factory()->create(['email' => 'victim@example.com']);

    fakeGoogleSocialiteUser('google-evil', 'victim@example.com', ['email_verified' => false]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
    expect(UserSocial::query()->where('user_id', $victim->id)->exists())->toBeFalse();
});

it('routes a two-factor-enabled user to the challenge instead of signing them in', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ]);
    UserSocial::create([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_id' => 'google-2fa',
    ]);

    fakeGoogleSocialiteUser('google-2fa', $user->email, ['email_verified' => true]);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->getAuthIdentifier());

    $this->assertGuest();
});
