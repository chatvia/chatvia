<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;

/**
 * The emailed reset link (Fortify's reset routes, with the page
 * FortifyServiceProvider sets up and our ResetUserPassword step): the page
 * comes with the token and email filled in, a valid token sets the new
 * password and leaves the person signed out, and the new password must be
 * strong and confirmed. An account nobody verified is claimed first.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('old-password')]);
    $this->token = Password::createToken($this->user);
});

it('shows the password reset page with the token and email filled in', function (): void {
    $this->get(route('password.reset', ['token' => $this->token, 'email' => 'test@example.com']))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/ResetPassword')
            ->where('token', $this->token)
            ->where('email', 'test@example.com'));
});

it('resets the password with a valid token and leaves the person signed out', function (): void {
    $this->post(route('password.update'), [
        'token' => $this->token,
        'email' => 'test@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    expect(auth()->check())->toBeFalse()
        ->and(Hash::check('new-password', $this->user->fresh()->password))->toBeTrue();
});

it('refuses a new password that is missing, unconfirmed or too short', function (string $password, string $confirmation): void {
    $this->post(route('password.update'), [
        'token' => $this->token,
        'email' => 'test@example.com',
        'password' => $password,
        'password_confirmation' => $confirmation,
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
})->with([
    'missing' => ['', ''],
    'not confirmed' => ['new-password', 'different-password'],
    'too short' => ['123', '123'],
]);

it('verifies an account nobody verified yet and drops the social sign-ins added before', function (): void {
    // The reset link proved the email; whoever registered it first must not
    // keep a way in.
    $this->user->forceFill(['email_verified_at' => null])->save();
    UserSocial::query()->create(['user_id' => $this->user->id, 'provider' => SocialProvider::Github, 'provider_id' => 'gh-squatter']);

    $this->post(route('password.update'), [
        'token' => $this->token,
        'email' => 'test@example.com',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    $user = $this->user->fresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->socials()->exists())->toBeFalse()
        ->and(Hash::check('new-password', $user->password))->toBeTrue();
});
