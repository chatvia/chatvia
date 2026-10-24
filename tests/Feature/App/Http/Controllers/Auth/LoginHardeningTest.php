<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Login-path rules that must hold on the way into the dashboard session:
 * deactivated accounts stay out (an open session too), chatters never get in,
 * unknown emails cost the same as known ones, and a sign-in that stops at the
 * two-factor challenge is not stamped as done. The magic link and the passkey
 * refuse a deactivated account too (MagicLinkSignInTest,
 * FortifyServiceProviderTest).
 */
describe('deactivated accounts', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create([
            'email' => 'off@example.test',
            'password' => Hash::make('right-password'),
        ]);
        app(DeactivateAccountAction::class)->execute($this->user, 'blocked by admin');
        $this->user->refresh();
    });

    it('cannot sign in with a password', function (): void {
        $this->from(route('login'))
            ->post(route('login'), ['email' => 'off@example.test', 'password' => 'right-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        expect(auth()->check())->toBeFalse();
    });

    it('is signed out of a session that was already open', function (): void {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        expect(auth()->check())->toBeFalse();
    });

});

it('does not open the dashboard with a chatter\'s password, even when a staff account shares the email', function (): void {
    // Chatters are not staff accounts: the dashboard login only reads `users`,
    // so a chatter's password never matches, whatever the email.
    Chatter::factory()->create(['email' => 'shared@example.test', 'password' => Hash::make('chatter-pass')]);
    $staff = User::factory()->create(['email' => 'shared@example.test', 'password' => Hash::make('staff-pass')]);

    $this->post(route('login'), ['email' => 'shared@example.test', 'password' => 'chatter-pass'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();

    $this->post(route('login'), ['email' => 'shared@example.test', 'password' => 'staff-pass'])
        ->assertRedirect(route('dashboard'));

    expect(Auth::guard('web')->id())->toBe($staff->id);
});

it('computes a password hash on the dashboard login even when the email is unknown', function (): void {
    // Without this, an unknown email returns faster than a wrong password and
    // the response time tells an attacker which emails have an account.
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    $this->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'any-password'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('does not stamp last_login_at when the login stops at the 2FA challenge', function (): void {
    $user = User::factory()->create([
        'email' => 'twofa@example.test',
        'password' => Hash::make('right-password'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post(route('login'), ['email' => 'twofa@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('two-factor.login'));

    expect(auth()->check())->toBeFalse()
        ->and(session('login.id'))->toBe($user->id)
        ->and($user->fresh()->last_login_at)->toBeNull();
});

it('signs in at once when a two-factor secret was set up but never confirmed', function (): void {
    // Two-factor sign-in starts only once the user confirms a code
    // (`confirm` in config/fortify.php), so a half-finished setup cannot lock
    // them out.
    $user = User::factory()->create([
        'email' => 'halfway@example.test',
        'password' => Hash::make('right-password'),
        'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        'two_factor_confirmed_at' => null,
    ]);

    $this->post(route('login'), ['email' => 'halfway@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
});
