<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Models\AgentActivity;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Models\SecurityAuditLog;

/**
 * Settings → Governance → Export: every tool call of the workspace's agents as
 * a CSV file, an Enterprise feature. Who may reach it (account.governance) is
 * checked with the other workspace routes.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

it('downloads the workspace tool calls as CSV and logs the export', function (): void {
    $agent = createAgent($this->owner, ['name' => 'Support bot']);
    AgentActivity::factory()->forAgent($agent)->create(['tool_name' => 'lookup_order', 'arguments' => ['order' => 42]]);
    AgentActivity::factory()->create(['tool_name' => 'stranger_tool']);

    $csv = $this->actingAs($this->owner)
        ->get(route('governance.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toStartWith('Time,Agent,User,Tool')
        ->toContain('Support bot')
        ->toContain('lookup_order')
        ->toContain('"{""order"":42}"')
        ->not->toContain('stranger_tool')
        ->and(SecurityAuditLog::query()->where('workspace_id', $this->owner->workspace()->id)->where('action', SecurityAuditAction::AuditExported->value)->exists())->toBeTrue();
});

it('refuses without an Enterprise license', function (): void {
    saveSettings(BrandSettings::class, ['license_key' => null]);

    $this->actingAs($this->owner)
        ->get(route('governance.export'))
        ->assertForbidden();
});
