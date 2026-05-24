<?php

use App\Auth\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

describe('User Registration', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('shows registration page to guests', function () {
        $response = $this->get(route('register'));

        $response->assertOk();
    });

    it('redirects authenticated users away from registration page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    });

    it('allows guest to register with valid data', function () {
        $data = [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms' => true,
            'policy' => true,
        ];

        $response = $this->post(route('register'), $data);

        $response->assertRedirect(route('dashboard'));

        expect(User::where('email', $data['email'])->exists())->toBeTrue();

        $this->assertAuthenticated();
    });

    it('sends email verification notification after registration', function () {
        $data = [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ];

        $this->post(route('register'), $data);

        $user = User::where('email', $data['email'])->first();

        Notification::assertSentTo($user, VerifyEmail::class);
    });

    it('requires name during registration', function () {
        $response = $this->post(route('register'), [
            'name' => '',
            'email' => fake()->safeEmail(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    });

    it('requires email during registration', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires valid email format', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => 'invalid-email',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires unique email', function () {
        $existingUser = User::factory()->create();

        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => $existingUser->email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires password during registration', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'password' => '',
            'password_confirmation' => '',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('requires password confirmation to match', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'password' => 'password',
            'password_confirmation' => 'different-password',
            'terms' => true,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('requires terms acceptance', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => false,
            'policy' => true,
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    });

    it('requires policy acceptance', function () {
        $response = $this->post(route('register'), [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
            'policy' => false,
        ]);

        $response->assertSessionHasErrors('policy');
        $this->assertGuest();
    });

    it('hashes password before storing', function () {
        $data = [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'PlainTextPassword',
            'password_confirmation' => 'PlainTextPassword',
            'terms' => true,
            'policy' => true,
        ];

        $this->post(route('register'), $data);

        $user = User::where('email', $data['email'])->first();

        expect($user->password)->not->toBe('PlainTextPassword');
        expect(Hash::check('PlainTextPassword', $user->password))->toBeTrue();
    });
});
