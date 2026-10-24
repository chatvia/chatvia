<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Models\SecurityAuditLog;
use Inertia\Testing\AssertableInertia;

/**
 * Settings → Security → Audit: a read-only view of the workspace's security
 * audit log, filtered by category. The account.security permission that
 * gates it is checked with the other workspace routes.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('shows the audit log of the workspace', function (): void {
    SecurityAuditLog::create([
        'workspace_id' => $this->owner->workspace()->id,
        'action' => SecurityAuditAction::AuthLogin->value,
        'outcome' => 'success',
        'user_name' => 'Ahmad',
        'ip' => '203.0.113.7',
    ]);

    $this->actingAs($this->owner)
        ->get(route('security.audit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('workspace/security/SecurityAudit')
            ->has('rows.data', 1)
            ->where('rows.data.0.action_label', SecurityAuditAction::AuthLogin->label()));
});

it('shows only the rows of the chosen category', function (): void {
    SecurityAuditLog::create(['workspace_id' => $this->owner->workspace()->id, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'success']);
    SecurityAuditLog::create(['workspace_id' => $this->owner->workspace()->id, 'action' => SecurityAuditAction::PolicyChanged->value, 'outcome' => 'success']);

    $this->actingAs($this->owner)
        ->get(route('security.audit', ['category' => 'auth']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.action_label', SecurityAuditAction::AuthLogin->label()));
});
