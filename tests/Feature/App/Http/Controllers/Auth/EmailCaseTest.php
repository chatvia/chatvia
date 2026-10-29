<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/**
 * Staff emails are stored lowercase (UserTest) and every lookup is a plain
 * `email = ?` on the lowercased input, so the unique index serves it.
 * Whatever case someone types on sign-up or the profile form, no second
 * account can take an email that differs only in case.
 */
beforeEach(fn () => withLicense());

it('signs up with a mixed-case email and refuses a second account differing only in case', function (): void {
    config(['chatvia.registration_enabled' => true]);
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'Ada.Signup@Example.TEST',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
    ])->assertRedirect(route('dashboard'));

    expect(User::query()->where('email', 'ada.signup@example.test')->exists())->toBeTrue();

    Auth::logout();

    $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Copy',
        'email' => 'ADA.SIGNUP@example.test',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
    ])->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'ada.signup@example.test')->count())->toBe(1);
});

it('refuses a profile email that another account holds in another case', function (): void {
    User::factory()->create(['email' => 'case-other@example.test']);
    $user = User::factory()->create(['email' => 'case-me@example.test']);
    ownerTeam($user);

    $this->actingAs($user)
        ->from(route('account.profile.edit'))
        ->patch(route('account.profile.update'), ['name' => $user->name, 'email' => 'Case-Other@Example.test'])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('case-me@example.test');
});
