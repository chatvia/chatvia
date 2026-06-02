<?php

use App\Workspace\Models\User;
use Illuminate\Support\Facades\Hash;

describe('Two-Factor Login', function (): void {
    it('redirects user with confirmed 2FA to the challenge page', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        expect(session('login.id'))->toBe($user->id);
    });

    it('signs user in immediately when 2FA is not configured', function (): void {
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
    });

    it('signs user in when 2FA secret exists but is not yet confirmed', function (): void {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->post(route('login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    });
});

describe('Two-Factor Management', function (): void {
    it('enables two-factor authentication for the user', function (): void {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        // Confirm password so Fortify's RequirePassword middleware lets us through.
        $this->actingAs($user);
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->post(route('two-factor.enable'));

        $response->assertRedirect();
        expect($user->fresh()->two_factor_secret)->not->toBeNull();
        expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
    });

    it('exposes recovery codes after enabling', function (): void {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user);
        session(['auth.password_confirmed_at' => time()]);

        $this->post(route('two-factor.enable'));

        $response = $this->get(route('two-factor.recovery-codes'), ['Accept' => 'application/json']);

        $response->assertOk();
        expect($response->json())->toBeArray()->toHaveCount(8);
    });
});
