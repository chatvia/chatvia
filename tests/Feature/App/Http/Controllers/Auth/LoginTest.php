<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

/**
 * The dashboard's password sign-in (Fortify's login route, with the page and
 * the credential check FortifyServiceProvider sets up): the sign-up link only
 * while sign-up is open, a wrong email or password answered the same way, a
 * completed sign-in stamped on the account, and five tries a minute per email
 * and address. The rules for deactivated accounts, chatters and two-factor
 * are in LoginHardeningTest.
 */
it('shows the login page, with the sign-up link only while sign-up is open', function (bool $open): void {
    config(['chatvia.registration_enabled' => $open]);

    $this->get(route('login'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
        ->component('auth/Login')
        ->where('canRegister', $open));
})->with(['sign-up open' => true, 'sign-up closed' => false]);

it('signs in with valid credentials and stamps the sign-in on the account', function (): void {
    $this->freezeTime();
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue()
        ->and($user->fresh()->last_login_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('refuses wrong credentials on the email field', function (string $email): void {
    User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('correct-password')]);

    $this->from(route('login'))
        ->post(route('login'), ['email' => $email, 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
})->with([
    'an unknown email' => 'nonexistent@example.com',
    'a wrong password' => 'test@example.com',
]);

it('locks out repeated failed logins for the same email', function (): void {
    User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('correct-password')]);

    // The `login` rate limiter (FortifyServiceProvider) allows 5 attempts a
    // minute per (email, IP); the 6th is refused with a 429.
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'wrong-password'])->assertStatus(302);
    }

    $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'wrong-password'])
        ->assertStatus(429);

    expect(auth()->check())->toBeFalse();
});
