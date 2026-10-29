<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Login-path rules that must hold on the way into the dashboard session:
 * deactivated accounts stay out (an open session too), chatters never get in
 * (the dashboard login only reads `users`), unknown emails cost the same as
 * known ones, and a sign-in that stops at the two-factor challenge is not
 * stamped as done, while a secret never confirmed (`confirm` in
 * config/fortify.php) does not lock anyone out. The magic link and the
 * passkey refuse a deactivated account too (HostedChatMagicLinkSignInTest,
 * FortifyServiceProviderTest).
 */
it('keeps a deactivated account out, by password and from a session already open', function (): void {
    $user = User::factory()->create(['email' => 'off@example.test', 'password' => Hash::make('right-password')]);
    app(DeactivateAccountAction::class)->execute($user, 'blocked by admin');

    $this->from(route('login'))
        ->post(route('login'), ['email' => 'off@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('does not open the dashboard with a chatter\'s password, even when a staff account shares the email', function (): void {
    Chatter::factory()->create(['email' => 'shared@example.test', 'password' => Hash::make('chatter-pass')]);
    $staff = User::factory()->create(['email' => 'shared@example.test', 'password' => Hash::make('staff-pass')]);

    $this->post(route('login'), ['email' => 'shared@example.test', 'password' => 'chatter-pass'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();

    $this->post(route('login'), ['email' => 'shared@example.test', 'password' => 'staff-pass'])
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('web')->id())->toBe($staff->id);
});

// Without the hash, an unknown email returns faster than a wrong password and
// the response time tells an attacker which emails have an account.
it('computes a password hash on the dashboard login even when the email is unknown', function (): void {
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    $this->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'any-password'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('stops at the 2FA challenge without stamping last_login_at, and signs in at once with an unconfirmed secret', function (): void {
    $confirmed = User::factory()->create([
        'email' => 'twofa@example.test',
        'password' => Hash::make('right-password'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);
    $halfway = User::factory()->create([
        'email' => 'halfway@example.test',
        'password' => Hash::make('right-password'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => null,
    ]);

    $this->post(route('login'), ['email' => 'twofa@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBe($confirmed->id)
        ->and($confirmed->fresh()->last_login_at)->toBeNull();

    $this->post(route('login'), ['email' => 'halfway@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($halfway))->toBeTrue();
});
