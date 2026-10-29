<?php

use App\Identity\Workspace\Enums\WorkspaceRole;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * The self-serve sign-up form (RegisteredUserController): open by default, a
 * 404 once the admin closes it (Settings → Workspaces), and a new account
 * comes with its own workspace. A taken email is refused whatever its case
 * (EmailCaseTest).
 */
beforeEach(fn () => withLicense());

it('answers 404 on both sign-up routes while sign-up is closed, and creates no account', function (): void {
    config(['chatvia.registration_enabled' => false]);

    $this->get(route('register'))->assertNotFound();
    $this->post(route('register.store'), [
        'name' => 'Ada',
        'email' => 'closed-signup@example.test',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
    ])->assertNotFound();

    expect(User::query()->where('email', 'closed-signup@example.test')->exists())->toBeFalse();
});

it('gives a new account its own workspace and default team, signs it in and asks it to verify the email', function (): void {
    config(['chatvia.registration_enabled' => true]);
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'new-signup@example.test',
        'password' => 'a-long-Password-123!',
        'password_confirmation' => 'a-long-Password-123!',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'new-signup@example.test')->sole();
    $workspace = $user->workspace();
    expect(auth()->user()?->is($user))->toBeTrue()
        ->and($user->workspaceRole())->toBe(WorkspaceRole::Owner)
        ->and($workspace->name)->toBe('Ada Lovelace')
        ->and($workspace->members()->pluck('users.id')->all())->toBe([$user->id])
        ->and($user->current_team_id)->toBe($user->primaryTeam()?->id)
        ->and($user->primaryTeam()?->workspace_id)->toBe($workspace->id)
        ->and($user->email_verified_at)->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});
