<?php

use App\Identity\Workspace\Actions\Account\CreateAdminUserAction;
use App\Identity\Workspace\Data\Account\CreateAdminUserData;
use App\Identity\Workspace\Exceptions\EmailAlreadyTakenException;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Notifications\MagicLinkNotification;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| Staff emails are stored lowercase
|--------------------------------------------------------------------------
|
| `users.email` holds only lowercase (a CHECK enforces it) and every lookup
| is a plain `email = ?` on the lowercased input, so the unique index serves
| it. Whatever case someone types, they reach the same single account.
*/

it('stores the email in lowercase', function (): void {
    $user = User::factory()->create(['email' => 'Mixed.Case@Example.TEST']);

    expect($user->fresh()->email)->toBe('mixed.case@example.test');
});

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

it('signs in to the dashboard whatever the case of the typed email', function (): void {
    $user = User::factory()->create(['email' => 'case-login@example.test']);

    $this->post(route('login'), ['email' => 'Case-Login@EXAMPLE.test', 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('checks credentials through the staff provider whatever the case, as the admin panel does', function (): void {
    User::factory()->create(['email' => 'case-admin@example.test', 'password' => Hash::make('secret-pass')]);

    expect(Auth::guard('admin')->validate(['email' => 'CASE-Admin@example.test', 'password' => 'secret-pass']))->toBeTrue();
});

it('emails a magic link to the account whatever the case', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'case-link@example.test']);

    $this->post(route('magic-link.send'), ['email' => 'Case-Link@Example.test'])->assertSessionHas('status');

    Notification::assertSentOnDemand(
        MagicLinkNotification::class,
        fn (MagicLinkNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'case-link@example.test',
    );
});

it('sends a reset link and resets the password whatever the case', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'case-reset@example.test']);

    $this->post(route('password.email'), ['email' => 'CASE-Reset@example.test'])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => 'Case-Reset@Example.test',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('refuses an admin account whose email differs only in case', function (): void {
    User::factory()->create(['email' => 'case-taken@example.test']);

    app(CreateAdminUserAction::class)->execute(new CreateAdminUserData(
        name: 'Copy',
        email: 'Case-Taken@Example.test',
        password: 'a-long-Password-123!',
    ));
})->throws(EmailAlreadyTakenException::class);

it('refuses a profile email that another account holds in another case', function (): void {
    User::factory()->create(['email' => 'case-other@example.test']);
    $user = User::factory()->create(['email' => 'case-me@example.test']);
    app(TeamService::class)->createDefaultTeam($user);

    $this->actingAs($user)
        ->from(route('account.profile.edit'))
        ->patch(route('account.profile.update'), ['name' => $user->name, 'email' => 'Case-Other@Example.test'])
        ->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('case-me@example.test');
});
