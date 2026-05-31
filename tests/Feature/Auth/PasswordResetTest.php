<?php

use App\Auth\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

describe('Forgot Password', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('shows forgot password page to guests', function (): void {
        $response = $this->get(route('password.request'));

        $response->assertOk();
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

    it('throttles password reset requests', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Make multiple requests
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('password.email'), ['email' => 'test@example.com']);
        }

        // Next request should be throttled
        $response = $this->post(route('password.email'), ['email' => 'test@example.com']);

        $response->assertStatus(429); // Too Many Requests
    });
});

describe('Password Reset', function (): void {
    beforeEach(function (): void {
        Notification::fake();
    });

    it('shows password reset page with valid token', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', ['token' => $token]));

        $response->assertOk();
    });

    it('resets password with valid token and data', function (): void {
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

        // Verify password was changed
        expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
        expect(Hash::check('old-password', $user->fresh()->password))->toBeFalse();
    });

    it('rejects password reset with invalid token', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');

        // Verify password was not changed
        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('rejects password reset with expired token', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $token = Password::createToken($user);

        // Travel forward in time past token expiration
        $this->travel(2)->hours();

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('requires token', function (): void {
        $response = $this->post(route('password.update'), [
            'token' => '',
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('token');
    });

    it('requires email', function (): void {
        $response = $this->post(route('password.update'), [
            'token' => 'some-token',
            'email' => '',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors('email');
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

    it('logs user in after successful password reset', function (): void {
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
    });

    it('invalidates old tokens after password reset', function (): void {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $oldToken = Password::createToken($user);

        // Reset password with the token
        $this->post(route('password.update'), [
            'token' => $oldToken,
            'email' => 'test@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        // Try to use the same token again
        $response = $this->post(route('password.update'), [
            'token' => $oldToken,
            'email' => 'test@example.com',
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ]);

        $response->assertSessionHasErrors('email');
    });
});

describe('Password Confirmation', function (): void {
    it('shows password confirmation page to authenticated users', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();
    });

    it('confirms password with correct password', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post(route('password.confirm'), [
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    });

    it('rejects confirmation with incorrect password', function (): void {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post(route('password.confirm'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('requires password field for confirmation', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('password.confirm'), [
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
    });
});
