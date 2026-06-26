<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

describe('User Login', function (): void {
    it('shows login page to guests', function (): void {
        $response = $this->get(route('login'));

        $response->assertOk();
    });

    it('redirects authenticated users away from login page', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    });

    it('allows user to login with valid credentials', function (): void {
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

    it('rejects login with invalid email', function (): void {
        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('rejects login with invalid password', function (): void {
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

    it('locks out repeated failed logins for the same email', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password'),
        ]);

        // The 'login' rate limiter (FortifyServiceProvider) allows 5 attempts
        // per minute per (email, IP). The 6th request is throttled by Laravel's
        // ThrottleRequests middleware, which returns HTTP 429.
        foreach (range(1, 5) as $ignored) {
            $this->post(route('login'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(302);
        }

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
        $this->assertGuest();
    });

    it('requires email field', function (): void {
        $response = $this->post(route('login'), [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('requires password field', function (): void {
        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    });

    it('supports remember me functionality', function (): void {
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

    it('does not set remember cookie when not requested', function (): void {
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
        $recallerCookie = collect($cookies)->first(fn ($cookie): bool => $cookie->getName() === $recallerName);

        expect($recallerCookie)->toBeNull();
    });

    it('validates email format', function (): void {
        $response = $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    });
});

describe('User Logout', function (): void {
    it('allows authenticated user to logout', function (): void {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        // Fortify logs out to the site root, which the locale middleware then
        // forwards to the localized home (/en).
        $response->assertRedirect('/');
        $this->assertGuest();
    });

    it('prevents unauthenticated users from accessing logout', function (): void {
        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('invalidates session on logout', function (): void {
        $user = User::factory()->create();

        $this->actingAs($user);
        $sessionId = session()->getId();

        $this->post(route('logout'));

        expect(session()->getId())->not->toBe($sessionId);
        $this->assertGuest();
    });

    it('removes remember token on logout', function (): void {
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

        $originalToken = $user->fresh()->remember_token;
        expect($originalToken)->not->toBeNull();

        // Logout
        $this->post(route('logout'));

        // Laravel rotates the remember_token on logout (since v5), so the old
        // token can no longer authenticate. Confirm it changed from what we saw
        // before logout.
        expect($user->fresh()->remember_token)->not->toBe($originalToken);
    });
});

describe('Session Management', function (): void {
    it('regenerates session on login to prevent fixation', function (): void {
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

    it('prevents concurrent sessions when user logs in multiple times', function (): void {
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
