<?php

use App\Admin\Clusters\Settings\Pages\ManageWorkspaceSettings;
use App\Admin\Settings\WorkspaceSettings;

/**
 * Settings → Workspaces: whether people may sign up, the message limit of a
 * new workspace, whether owners may download their data or delete their
 * account, and how long conversations and notifications are kept, with
 * a floor no workspace may go below.
 */
beforeEach(function (): void {
    $this->admin = adminUser();
});

it('shows the workspace values in use', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->assertSee('Sign-up open · no message limit')
        ->assertSee('Kept 366 days')
        ->assertSee('Sign-up and limits')
        ->assertSee('Download on · Delete on')
        ->assertSchemaStateSet(['registration_enabled' => true, 'new_workspace_message_limit' => null]);
});

it('saves the workspace values, an off switch included', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['registration_enabled' => false, 'new_workspace_message_limit' => 500, 'retention_days' => 90])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageWorkspaceSettings::getUrl())
        ->assertSee('Sign-up closed · 500 messages a month');

    expect(savedSetting(WorkspaceSettings::class, 'registration_enabled'))->toBeFalse()
        ->and(savedSetting(WorkspaceSettings::class, 'new_workspace_message_limit'))->toBe(500)
        ->and(savedSetting(WorkspaceSettings::class, 'retention_days'))->toBe(90)
        ->and(config('chatvia.registration_enabled'))->toBeFalse();
});

it('refuses a retention of no days', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['retention_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['retention_days']);

    expect(savedSetting(WorkspaceSettings::class, 'retention_days'))->toBeNull();
});

it('refuses a fewest-days value above the days to keep conversations', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['retention_days' => 30, 'retention_floor_days' => 60])
        ->call('save')
        ->assertHasFormErrors(['retention_floor_days']);

    expect(savedSetting(WorkspaceSettings::class, 'retention_floor_days'))->toBeNull();
});

it('keeps notifications for the days an admin chooses, at least one', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['notification_retention_days' => 0])
        ->call('save')
        ->assertHasFormErrors(['notification_retention_days']);

    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['notification_retention_days' => 14])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(savedSetting(WorkspaceSettings::class, 'notification_retention_days'))->toBe(14)
        ->and(config('chatvia.notifications.retention_days'))->toBe(14);
});

it('saves the owner account switches', function (): void {
    adminPage(ManageWorkspaceSettings::class, $this->admin)
        ->fillForm(['owner_data_export' => false, 'owner_account_deletion' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->actingAs($this->admin, 'admin')
        ->get(ManageWorkspaceSettings::getUrl())
        ->assertSee('Download off · Delete off');

    expect(savedSetting(WorkspaceSettings::class, 'owner_data_export'))->toBeFalse()
        ->and(savedSetting(WorkspaceSettings::class, 'owner_account_deletion'))->toBeFalse()
        ->and(config('chatvia.account.data_export'))->toBeFalse()
        ->and(config('chatvia.account.deletion'))->toBeFalse();
});
