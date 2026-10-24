<?php

use App\Identity\Workspace\Models\User;

/**
 * `chatvia:admin:create-user` is how a self-hosted install creates its first
 * account. What the account gets (verified, an admin, owning its default
 * team) is CreateAdminUserActionTest's; here are the command's own
 * branches: a refused email fails, and --if-missing / --if-no-admin let a
 * restart run it again without a second account.
 */
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
