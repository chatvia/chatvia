<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Models\SecurityAuditLog;

/**
 * Settings → Security → Audit → Export: the workspace's security audit log as
 * a CSV file, an Enterprise feature, with every cell a spreadsheet would run
 * made safe. Who may reach it (account.security) and the license gate are
 * checked with the other workspace routes.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('downloads the workspace rows as CSV with the viewer filters, and logs the export', function (): void {
    $workspaceId = $this->owner->workspace()->id;
    SecurityAuditLog::create(['workspace_id' => $workspaceId, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'success', 'user_name' => 'Ahmad', 'ip' => '203.0.113.7']);
    SecurityAuditLog::create(['workspace_id' => $workspaceId, 'action' => SecurityAuditAction::PolicyChanged->value, 'outcome' => 'success', 'user_name' => '=HYPERLINK("x")']);
    SecurityAuditLog::create(['workspace_id' => User::factory()->create()->workspace()->id, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'success', 'user_name' => 'Stranger']);

    $response = $this->actingAs($this->owner)
        ->get(route('security.audit.export', ['category' => 'auth']))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $csv = $response->streamedContent();

    expect($csv)->toStartWith('Time,Action,Description')
        ->toContain('Ahmad')
        ->toContain('203.0.113.7')
        ->not->toContain('HYPERLINK')
        ->not->toContain('Stranger')
        ->and(SecurityAuditLog::query()->where('workspace_id', $workspaceId)->where('action', SecurityAuditAction::AuditExported->value)->exists())->toBeTrue();
});

it('makes a cell that a spreadsheet would run safe', function (): void {
    SecurityAuditLog::create(['workspace_id' => $this->owner->workspace()->id, 'action' => SecurityAuditAction::AuthLogin->value, 'outcome' => 'success', 'user_name' => '=HYPERLINK("x")']);

    $csv = $this->actingAs($this->owner)->get(route('security.audit.export'))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});
