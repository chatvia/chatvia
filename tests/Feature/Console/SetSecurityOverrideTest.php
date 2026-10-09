<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Support\Enums\User\UserType;

it('grants, revokes and resets the security suite for a workspace', function (): void {
    $workspace = User::factory()->create(['type' => UserType::Workspace, 'email' => 'owner@example.com']);

    $this->artisan('app:security-override', ['workspace' => 'owner@example.com', 'state' => 'grant'])->assertSuccessful();
    expect(WorkspaceSecurityPolicy::forWorkspace($workspace->id)->security_override)->toBeTrue();

    $this->artisan('app:security-override', ['workspace' => (string) $workspace->id, 'state' => 'revoke'])->assertSuccessful();
    expect(WorkspaceSecurityPolicy::forWorkspace($workspace->id)->security_override)->toBeFalse();

    $this->artisan('app:security-override', ['workspace' => 'owner@example.com', 'state' => 'reset'])->assertSuccessful();
    expect(WorkspaceSecurityPolicy::forWorkspace($workspace->id)->security_override)->toBeNull();
});

it('rejects an unknown state or workspace', function (): void {
    User::factory()->create(['type' => UserType::Workspace, 'email' => 'owner@example.com']);

    $this->artisan('app:security-override', ['workspace' => 'owner@example.com', 'state' => 'maybe'])->assertFailed();
    $this->artisan('app:security-override', ['workspace' => 'nobody@example.com', 'state' => 'grant'])->assertFailed();
});
