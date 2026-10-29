<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The dashboard's password sign-in (Fortify's login route, with the credential
 * check FortifyServiceProvider sets up): a completed sign-in is stamped on the
 * account, a wrong email or password is answered the same way, and the
 * `login` rate limiter allows five tries a minute per email and address. The
 * rules for deactivated accounts, chatters and two-factor are in
 * LoginHardeningTest.
 */
beforeEach(fn () => withLicense());

it('signs in with valid credentials and stamps the sign-in on the account', function (): void {
    $this->freezeTime();
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue()
        ->and($user->fresh()->last_login_at?->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('refuses wrong credentials on the email field and locks out the sixth try for the same email', function (): void {
    User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('correct-password')]);

    $this->from(route('login'))
        ->post(route('login'), ['email' => 'nonexistent@example.com', 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
    foreach (range(1, 5) as $attempt) {
        $this->from(route('login'))
            ->post(route('login'), ['email' => 'test@example.com', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }
    $this->post(route('login'), ['email' => 'test@example.com', 'password' => 'wrong-password'])
        ->assertStatus(429);

    expect(auth()->check())->toBeFalse();
});
