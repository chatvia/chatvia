<?php

use App\Http\Auth\MagicLinkSignIn;
use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Magic\TwoFactorAwareLoginAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use MagicLink\Security\Serializable\ActionSerializable;

/**
 * Login-path rules that must hold on every way into the dashboard session:
 * deactivated accounts stay out, unknown emails cost the same as known ones,
 * and last_login_at reflects a real, completed sign-in.
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

        $this->assertGuest();
    });

    it('cannot sign in with a magic link sent before the deactivation', function (): void {
        $response = (new MagicLinkSignIn($this->user->id, route('dashboard')))->run();

        expect($response->getTargetUrl())->toBe(route('login'))
            ->and(Auth::guard('web')->check())->toBeFalse();
    });

    it('cannot sign in with a passkey', function (): void {
        $passkey = new Passkey;
        $passkey->setRelation('user', $this->user);

        expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeFalse();
    });

    it('is signed out of a session that was already open', function (): void {
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    });

    it('cannot open the dashboard', function (): void {
        expect($this->user->canAccessDashboard())->toBeFalse();
    });
});

it('lets an active user sign in with a passkey', function (): void {
    $passkey = new Passkey;
    $passkey->setRelation('user', User::factory()->create());

    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeTrue();
});

it('does not let a chatter sign in with a passkey', function (): void {
    $passkey = new Passkey;
    $passkey->setRelation('user', User::factory()->chatter(User::factory()->workspace()->create())->create());

    expect(Passkeys::allowsLogin(Request::create('/'), $passkey))->toBeFalse();
});

it('computes a password hash on the dashboard login even when the email is unknown', function (): void {
    // Without this, an unknown email returns faster than a wrong password and
    // the response time tells an attacker which emails have an account.
    Hash::shouldReceive('make')->once()->andReturn('hashed');

    $this->post(route('login'), ['email' => 'nobody@example.test', 'password' => 'any-password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('stamps last_login_at only once the sign-in completes', function (): void {
    $user = User::factory()->create([
        'email' => 'stamp@example.test',
        'password' => Hash::make('right-password'),
    ]);

    $this->post(route('login'), ['email' => 'stamp@example.test', 'password' => 'right-password'])
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->last_login_at)->not->toBeNull();
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

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('still opens a magic link stored under the action\'s previous class name', function (): void {
    // Links live 15 minutes and store their action serialized, so a link
    // emailed just before the rename must keep working.
    $user = User::factory()->create();
    $stored = ActionSerializable::serialize(new TwoFactorAwareLoginAction($user->id, route('dashboard')));

    $response = ActionSerializable::unserialize($stored)->run();

    expect($response->getTargetUrl())->toBe(route('dashboard'))
        ->and(Auth::guard('web')->id())->toBe($user->id);
});
