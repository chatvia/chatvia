<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia;

describe('Forgot Password', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('shows the forgot password page to guests', function (): void {
        $this->get(route('password.request'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/ForgotPassword'));
    });

    it('sends password reset link to valid email', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    });

    it('does not reveal if email exists in database', function (): void {
        // Non-existent email should show same response as valid email
        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        // Should not show error, just status message
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    });

    it('requires email field', function (): void {
        $response = $this->post(route('password.email'), [
            'email' => '',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('requires valid email format', function (): void {
        $response = $this->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('takes five reset requests a minute and refuses the sixth', function (): void {
        User::factory()->create(['email' => 'test@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.email'), ['email' => 'test@example.com'])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'test@example.com'])->assertStatus(429);
    });
});

describe('Password Reset', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('shows the password reset page with the token and email filled in', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'test@example.com']))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('auth/ResetPassword')
                ->where('token', $token)
                ->where('email', 'test@example.com'));
    });

    it('resets the password with a valid token and leaves the user signed out', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        expect(auth()->check())->toBeFalse();

        // Verify password was changed
        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
        expect(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
    });

    it('requires password', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('requires password confirmation', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('requires minimum password length', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);
        $token = Password::createToken($user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertSessionHasErrors('password');
    });
});
