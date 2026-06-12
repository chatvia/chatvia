<?php

use App\Identity\Workspace\Actions\Fortify\ResetUserPassword;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;

it('redirects a flagged user from the dashboard to the set-password page', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.setup'));
});

it('shows the set-password page to a flagged user', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->get(route('password.setup'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('auth/SetPassword')
            ->where('email', $user->email));
});

it('keeps logout reachable for a flagged user', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('does not touch users without the flag', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('redirects an unflagged user away from the set-password page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('password.setup'))
        ->assertRedirect(route('dashboard'));
});

it('rejects a set-password POST from an unflagged user', function (): void {
    // Without the flag this endpoint would be a password change that skips
    // the current-password check — it must be forbidden.
    $user = User::factory()->create([
        'password' => Hash::make('original-password'),
    ]);

    $this->actingAs($user)
        ->post(route('password.setup.store'), [
            'password' => 'attacker-chosen-pw',
            'password_confirmation' => 'attacker-chosen-pw',
        ])
        ->assertForbidden();

    expect(Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});

it('sets the password, clears the flag, and unlocks the app', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->post(route('password.setup.store'), [
            'password' => 'my-chosen-password',
            'password_confirmation' => 'my-chosen-password',
        ])
        ->assertRedirect(route('dashboard'));

    expect(Hash::check('my-chosen-password', $user->fresh()->password))->toBeTrue()
        ->and(app(PasswordSetup::class)->isRequired($user))->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('validates the new password against the default password rules', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->from(route('password.setup'))
        ->post(route('password.setup.store'), [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    expect(app(PasswordSetup::class)->isRequired($user))->toBeTrue();
});

it('requires the password confirmation to match', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    $this->actingAs($user)
        ->from(route('password.setup'))
        ->post(route('password.setup.store'), [
            'password' => 'my-chosen-password',
            'password_confirmation' => 'something-else',
        ])
        ->assertSessionHasErrors('password');
});

it('redirects guests to the login page', function (): void {
    $this->get(route('password.setup'))
        ->assertRedirect(route('login'));
});

it('clears the flag when the password is reset through forgot-password', function (): void {
    $user = User::factory()->create();
    app(PasswordSetup::class)->flag($user);

    app(ResetUserPassword::class)->reset($user, [
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ]);

    expect(app(PasswordSetup::class)->isRequired($user))->toBeFalse()
        ->and(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});
