<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\HttpFoundation\Cookie;

describe('User Login', function (): void {
    it('shows the login page, with the sign-up link only while sign-up is open', function (bool $open): void {
        config(['chatvia.registration_enabled' => $open]);

        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/Login')
            ->where('canRegister', $open));
    })->with(['sign-up open' => true, 'sign-up closed' => false]);

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
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Ensure remember cookie is not set
        $recallerName = Auth::guard()->getRecallerName();
        $cookies = $response->headers->getCookies();
        $recallerCookie = collect($cookies)->first(fn (Cookie $cookie): bool => $cookie->getName() === $recallerName);

        expect($recallerCookie)->toBeNull();
    });
});
