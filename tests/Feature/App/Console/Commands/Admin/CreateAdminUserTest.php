<?php

use App\Identity\Workspace\Models\User;

/**
 * `chatvia:admin:create-user` is how a self-hosted install creates its first
 * account. What the account gets (verified, an admin, owning its default
 * team) is CreateAdminUserActionTest's; here the command creates the admin
 * given on the command line, and fails without a license key once the
 * install holds its one free workspace.
 */
beforeEach(fn () => withLicense());

it('creates the admin given on the command line', function (): void {
    $this->artisan('chatvia:admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
    ])->assertSuccessful();

    expect(User::query()->where('email', 'first-admin@self-hosted.test')->sole())
        ->isAdmin()->toBeTrue()
        ->name->toBe('First Admin');
});

it('fails and creates no account when the install already has its workspace and no license key', function (): void {
    withoutLicense();

    $this->artisan('chatvia:admin:create-user', [
        'email' => 'second-admin@self-hosted.test',
        'name' => 'Second Admin',
        'password' => 'a-long-password',
    ])->assertFailed();

    expect(User::query()->where('email', 'second-admin@self-hosted.test')->exists())->toBeFalse();
});
