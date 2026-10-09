<?php

use App\Identity\Workspace\Enums\UserType;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\MessageAdmission;
use Domain\Billing\Services\MessageSendGate;

/**
 * `admin:create-user` is how a self-hosted install creates its first account,
 * so the account must be complete: verified, an admin, and owning the default
 * team it builds agents in.
 */
it('creates a verified admin with a default team', function (): void {
    config(['billing.enabled' => false]);

    $this->artisan('admin:create-user', [
        'email' => 'first-admin@self-hosted.test',
        'name' => 'First Admin',
        'password' => 'a-long-password',
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'first-admin@self-hosted.test')->sole();
    $team = $admin->ownedTeams()->sole();

    expect($admin->type)->toBe(UserType::Admin)
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->current_team_id)->toBe($team->id)
        ->and($team->roles()->where('code', 'owner')->exists())->toBeTrue()
        ->and(app(MessageSendGate::class)->admit($admin))->toBe(MessageAdmission::Allowed);
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
