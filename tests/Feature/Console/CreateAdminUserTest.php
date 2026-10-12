<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;

/**
 * `admin:create-user` is how a self-hosted install creates its first account,
 * so the account must be complete: verified, an admin, and owning the default
 * team it builds agents in.
 */
it('creates a verified admin with a default team and no message limit', function (): void {
    $this->artisan('admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'first-admin@self-hosted.test')->sole();
    $team = $admin->ownedTeams()->sole();

    expect($admin->isAdmin())->toBeTrue()
        ->and($admin->isWorkspaceOwner())->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->current_team_id)->toBe($team->id)
        ->and($team->roles()->where('code', 'owner')->exists())->toBeTrue()
        ->and(app(MessageAllowance::class)->canSend($admin->workspace()->id))->toBeTrue();
});

it('refuses an email that is already taken', function (): void {
    $existing = User::factory()->create();

    $this->artisan('admin:create-user', [
        'email' => $existing->email,
        'name' => 'Someone',
        'password' => 'a-long-password',
    ])->assertFailed();

    expect(User::query()->where('email', $existing->email)->count())->toBe(1);
});

it('leaves a taken email alone and succeeds with --if-missing', function (): void {
    $existing = User::factory()->create(['name' => 'Original Name']);

    $this->artisan('admin:create-user', [
        'email' => $existing->email,
        'name' => 'Someone',
        'password' => 'a-long-password',
        '--if-missing' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', $existing->email)->sole()->name)->toBe('Original Name');
});

it('creates no second admin with --if-no-admin, so a restart after an email change or a restore adds no account', function (): void {
    User::factory()->create(['is_admin' => true, 'email' => 'changed-email@self-hosted.test']);

    $this->artisan('admin:create-user', [
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

    $this->artisan('admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
        '--if-no-admin' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', 'first-admin@self-hosted.test')->sole()->isAdmin())->toBeTrue();
});
