<?php

use App\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

describe('User Login', function () {
    it('shows login page to guests', function () {
        $response = $this->get(route('login'));

        $response->assertOk();
    });

    it('redirects authenticated users away from login page', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    });

    it('allows user to login with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    });

    it('rejects login with invalid email', function () {
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('rejects login with invalid password', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires email field', function () {
        $response = $this->post(route('login'), [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires password field', function () {
        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('supports remember me functionality', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Check if remember cookie is set
        $response->assertCookie(Auth::guard()->getRecallerName());
    });

    it('does not set remember cookie when not requested', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'privacy' => true,
            'terms' => true,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Ensure remember cookie is not set
        $recallerName = Auth::guard()->getRecallerName();
        $cookies = $response->headers->getCookies();
        $recallerCookie = collect($cookies)->first(fn ($cookie) => $cookie->getName() === $recallerName);

        expect($recallerCookie)->toBeNull();
    });

    it('validates email format', function () {
        $response = $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});

describe('User Logout', function () {
    it('allows authenticated user to logout', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    });

    it('prevents unauthenticated users from accessing logout', function () {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('invalidates session on logout', function () {
        $user = User::factory()->create();

        $this->actingAs($user);
        $sessionId = session()->getId();

        $this->post(route('logout'));

        expect(session()->getId())->not->toBe($sessionId);
        $this->assertGuest();
    });

    it('removes remember token on logout', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Login with remember me
        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'remember' => true,
        ]);

        expect($user->fresh()->remember_token)->not->toBeNull();

        // Logout
        $this->post(route('logout'));

        expect($user->fresh()->remember_token)->toBeNull();
    });
});

describe('Session Management', function () {
    it('regenerates session on login to prevent fixation', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        $oldSessionId = session()->getId();

        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        expect(session()->getId())->not->toBe($oldSessionId);
    });

    it('prevents concurrent sessions when user logs in multiple times', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // First login
        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $firstSessionId = session()->getId();

        // Logout
        $this->post(route('logout'));

        // Second login
        $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        expect(session()->getId())->not->toBe($firstSessionId);
    });
});
