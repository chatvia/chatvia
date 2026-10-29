<?php

use App\Admin\Clusters\Settings\Pages\ManageWorkspaceSettings;
use App\Admin\Settings\WorkspaceSettings;

/**
 * Settings → Workspaces: whether people may sign up, the message limit of a
 * new workspace and how long conversations are kept are saved into the
 * config the app reads, and a retention floor above the days to keep
 * conversations is refused.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('saves the workspace values, an off switch included', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['registration_enabled' => false, 'new_workspace_message_limit' => 500, 'retention_days' => 90])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeFalse()
        ->and(savedSetting(WorkspaceSettings::class, 'new_workspace_message_limit'))->toBe(500)
        ->and(savedSetting(WorkspaceSettings::class, 'retention_days'))->toBe(90)
        ->and(config('chatvia.registration_enabled'))->toBeFalse();
});

it('refuses a fewest-days value above the days to keep conversations', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['retention_days' => 30, 'retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['retention_floor_days']);

    expect(savedSetting(WorkspaceSettings::class, 'retention_floor_days'))->toBeNull();
});
