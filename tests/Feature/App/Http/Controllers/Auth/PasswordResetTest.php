<?php

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * The emailed reset link (Fortify's reset routes and our ResetUserPassword
 * step): a valid token sets the new password and leaves the person signed
 * out. The link proves the email, so an account nobody verified is verified
 * and loses the social sign-ins whoever registered it first added.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'test@example.com', 'password' => Hash::make('old-password')]);
    $this->token = Password::createToken($this->user);
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

it('verifies an account nobody verified yet and drops the social sign-ins added before', function (): void {
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
