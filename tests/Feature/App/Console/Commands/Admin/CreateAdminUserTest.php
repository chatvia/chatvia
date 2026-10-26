<?php

use App\Identity\Workspace\Models\User;

/**
 * `chatvia:admin:create-user` is how a self-hosted install creates its first
 * account. What the account gets (verified, an admin, owning its default
 * team) is CreateAdminUserActionTest's; here are the command's own
 * branches: a refused email fails, and --if-missing / --if-no-admin let a
 * restart run it again without a second account.
 */
// Creating workspaces past the first one needs a license key; the free-use
// limit itself is covered in WorkspaceAllowanceTest.
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

it('fails when the install already has its workspace and no license key', function (): void {
    withoutLicense();

    $this->artisan('chatvia:admin:create-user', [
        'email' => 'second-admin@self-hosted.test',
        'name' => 'Second Admin',
        'password' => 'a-long-password',
    ])->expectsOutputToContain('Without a ChatVia.ai license key it holds one')
        ->assertFailed();

    expect(User::query()->where('email', 'second-admin@self-hosted.test')->exists())->toBeFalse();
});

it('asks for the email, name and password when run with no arguments', function (): void {
    $this->artisan('chatvia:admin:create-user')
        ->expectsQuestion('Email address of the admin user', 'asked-admin@self-hosted.test')
        ->expectsQuestion('Name of the admin user', 'Asked Admin')
        ->expectsQuestion('Enter password for the admin user', 'a-long-password')
        ->assertSuccessful();

    expect(User::query()->where('email', 'asked-admin@self-hosted.test')->sole())
        ->isAdmin()->toBeTrue()
        ->name->toBe('Asked Admin');
});

it('refuses an email that is already taken', function (): void {
    $existing = User::factory()->create();

    $this->artisan('chatvia:admin:create-user', [
        'email' => $existing->email,
        'name' => 'Someone',
        'password' => 'a-long-password',
    ])->assertFailed();

    expect(User::query()->where('email', $existing->email)->count())->toBe(1);
});

it('leaves a taken email alone and succeeds with --if-missing', function (): void {
    $existing = User::factory()->create(['name' => 'Original Name']);

    $this->artisan('chatvia:admin:create-user', [
        'email' => $existing->email,
        'name' => 'Someone',
        'password' => 'a-long-password',
        '--if-missing' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', $existing->email)->sole()->name)->toBe('Original Name');
});

it('creates no second admin with --if-no-admin, so a restart after an email change or a restore adds no account', function (): void {
    User::factory()->create(['is_admin' => true, 'email' => 'changed-email@self-hosted.test']);

    $this->artisan('chatvia:admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
        '--if-no-admin' => true,
    ])->expectsOutputToContain('already exists')->assertSuccessful();

    expect(User::query()->where('email', 'first-admin@self-hosted.test')->exists())->toBeFalse();
});

it('creates the first admin with --if-no-admin when there is none', function (): void {
    // The test database is seeded with an admin.
    User::query()->where('is_admin', true)->update(['is_admin' => false]);

    $this->artisan('chatvia:admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
        '--if-no-admin' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', 'first-admin@self-hosted.test')->sole()->isAdmin())->toBeTrue();
});
