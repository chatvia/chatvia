<?php

use App\Http\Controllers\Settings\Account\ApiKeyController;
use App\Http\Controllers\Settings\Account\DataExportController;
use App\Http\Controllers\Settings\Account\DeleteAccountController;
use App\Http\Controllers\Settings\Account\PasskeyController;
use App\Http\Controllers\Settings\Account\PasswordController;
use App\Http\Controllers\Settings\Account\PreferencesController;
use App\Http\Controllers\Settings\Account\ProfileController;
use App\Http\Controllers\Settings\Account\SocialController;
use App\Http\Controllers\Settings\Account\TwoFactorController;
use App\Http\Controllers\Settings\Connector\Catalog\ConnectController;
use App\Http\Controllers\Settings\Connector\Catalog\ConnectorController;
use App\Http\Controllers\Settings\Connector\Manage\ConnectorAgentController;
use App\Http\Controllers\Settings\Connector\Manage\ConnectorToolController;
use App\Http\Controllers\Settings\Connector\Manage\SetConnectorToolPermissionController;
use App\Http\Controllers\Settings\Connector\Manage\ToggleConnectorController;
use App\Http\Controllers\Settings\Connector\Provisioning\CustomAddController;
use App\Http\Controllers\Settings\Connector\Provisioning\OAuthStartController;
use App\Http\Controllers\Settings\Connector\Provisioning\RefreshConnectorController;
use App\Http\Controllers\Settings\Function\FunctionAgentController;
use App\Http\Controllers\Settings\Function\FunctionController;
use App\Http\Controllers\Settings\Function\ToggleFunctionController;
use App\Http\Controllers\Settings\Governance\GovernanceAuditController;
use App\Http\Controllers\Settings\Member\SettingsMemberController;
use App\Http\Controllers\Settings\Prompt\PromptController;
use App\Http\Controllers\Settings\Usage\SettingsUsageController;
use App\Http\Controllers\Team\Crud\TeamDeleteController;
use App\Http\Controllers\Team\Crud\TeamLeaveController;
use App\Http\Controllers\Team\Crud\TeamsHomeController;
use App\Http\Controllers\Team\Crud\TeamShowController;
use App\Http\Controllers\Team\Crud\TeamsStoreController;
use App\Http\Controllers\Team\Crud\TeamUpdateController;
use App\Http\Controllers\Team\Member\TeamCancelInvitationController;
use App\Http\Controllers\Team\Member\TeamInviteMemberController;
use App\Http\Controllers\Team\Member\TeamRemoveMemberController;
use App\Http\Controllers\Team\Member\TeamUpdateMemberRoleController;
use App\Http\Controllers\Team\Roles\TeamRoleCreateShowController;
use App\Http\Controllers\Team\Roles\TeamRoleDeleteController;
use App\Http\Controllers\Team\Roles\TeamRoleEditShowController;
use App\Http\Controllers\Team\Roles\TeamRoleStoreController;
use App\Http\Controllers\Team\Roles\TeamRoleUpdateController;
use App\Http\Controllers\Team\Switching\TeamListController;
use App\Http\Controllers\Team\Switching\TeamSwitchController;
use App\Http\Middleware\Auth\DenyIfEmployee;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can.access.dashboard'])->group(function (): void {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    // Account deletion lives on its own page in the Account section so the
    // destructive flow has the user's full attention. Two-factor: re-enter the
    // password (request) → open the signed email link (confirm, 30-min expiry)
    // → final explicit click (destroy). Workspace owners only — gated in the
    // controller + FormRequest.
    Route::get('settings/delete-account', [DeleteAccountController::class, 'edit'])->name('settings.account.delete');
    Route::post('settings/delete-account', [DeleteAccountController::class, 'request'])
        ->middleware('throttle:5,60')
        ->name('settings.account.delete.request');
    Route::get('settings/delete-account/confirm/{userId}', [DeleteAccountController::class, 'confirm'])
        ->whereNumber('userId')
        ->middleware('signed')
        ->name('settings.account.delete.confirm');
    Route::delete('settings/delete-account', [DeleteAccountController::class, 'destroy'])->name('settings.account.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');

    Route::get('settings/two-factor', [TwoFactorController::class, 'edit'])->name('settings.two-factor.edit');
    Route::get('settings/passkeys', [PasskeyController::class, 'edit'])->name('settings.passkeys.edit');

    Route::get('settings/social', [SocialController::class, 'edit'])->name('settings.social.edit');
    Route::get('settings/social/{provider}/connect', [SocialController::class, 'connect'])
        ->middleware('throttle:10,1')
        ->name('settings.social.connect');
    Route::delete('settings/social/{provider}', [SocialController::class, 'disconnect'])
        ->middleware('throttle:10,1')
        ->name('settings.social.disconnect');

    Route::get('settings/api-keys', [ApiKeyController::class, 'edit'])->name('api-keys.edit');
    Route::post('settings/api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('settings/api-keys/{tokenId}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    // GDPR Art. 15/20 — let the user request a copy of their personal data
    // and download it for 24 hours before it's pruned by
    // `app:prune-expired-data-exports`.
    Route::get('settings/data-export', [DataExportController::class, 'edit'])
        ->name('settings.data-export.edit');
    Route::post('settings/data-export', [DataExportController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('settings.data-export.store');
    Route::get('settings/data-export/{export}/download', [DataExportController::class, 'download'])
        ->name('settings.data-export.download');
    Route::delete('settings/data-export/{export}', [DataExportController::class, 'destroy'])
        ->name('settings.data-export.destroy');

    // Account-level Connectors — the same shape as the per-agent connectors
    // page, but at the user level. Featured catalog connectors + custom MCP
    // servers, with the existing OAuth/token flows pointed at the user.
    Route::get('settings/connectors', [ConnectorController::class, 'edit'])->name('settings.connectors.edit');
    Route::get('settings/connectors/data', [ConnectorController::class, 'index'])->name('settings.connectors.data');
    Route::post('settings/connectors/{slug}/connect', ConnectController::class)->name('settings.connectors.connect');
    Route::get('settings/connectors/{slug}/oauth/start', OAuthStartController::class)->name('settings.connectors.oauth.start');
    Route::post('settings/connectors/custom/add', CustomAddController::class)->name('settings.connectors.custom.add');
    Route::post('settings/connectors/{slug}/enable', ToggleConnectorController::class)->name('settings.connectors.enable');
    Route::delete('settings/connectors/{slug}', [ConnectorController::class, 'destroy'])->name('settings.connectors.destroy');
    Route::post('settings/connectors/{slug}/refresh', RefreshConnectorController::class)->name('settings.connectors.refresh');
    Route::get('settings/connectors/{slug}/tools', ConnectorToolController::class)->name('settings.connectors.tools');

    // Per-tool permission (allow / approval / blocked) — the tri-state shown
    // in the Connectors UI.
    Route::put('settings/connectors/{slug}/tools/{tool}/permission', SetConnectorToolPermissionController::class)
        ->whereNumber('tool')
        ->name('settings.connectors.tools.permission');

    // Manage which agents use a connector (from the Connectors sheet).
    Route::get('settings/connectors/{slug}/agents', [ConnectorAgentController::class, 'index'])->name('settings.connectors.agents.index');
    Route::post('settings/connectors/{slug}/agents/{agent}', [ConnectorAgentController::class, 'store'])->name('settings.connectors.agents.attach');
    Route::delete('settings/connectors/{slug}/agents/{agent}', [ConnectorAgentController::class, 'destroy'])->name('settings.connectors.agents.detach');

    Route::get('settings/functions', [FunctionController::class, 'edit'])->name('settings.functions.edit');
    Route::post('settings/functions', [FunctionController::class, 'store'])->name('settings.functions.store');
    Route::patch('settings/functions/{external_id}', [FunctionController::class, 'update'])->name('settings.functions.update');
    Route::post('settings/functions/{external_id}/toggle', ToggleFunctionController::class)->name('settings.functions.toggle');
    Route::delete('settings/functions/{external_id}', [FunctionController::class, 'destroy'])->name('settings.functions.destroy');

    // Manage which agents use a function (from the Functions sheet).
    Route::get('settings/functions/{external_id}/agents', [FunctionAgentController::class, 'index'])->name('settings.functions.agents.index');
    Route::post('settings/functions/{external_id}/agents/{agent}', [FunctionAgentController::class, 'store'])->name('settings.functions.agents.attach');
    Route::delete('settings/functions/{external_id}/agents/{agent}', [FunctionAgentController::class, 'destroy'])->name('settings.functions.agents.detach');

    // Account-level reusable prompts (Prompt Manager). Authored here, attached
    // to agents from each agent's Persona page via `agent_prompt_id`.
    Route::get('settings/prompts', [PromptController::class, 'edit'])->name('settings.prompts.edit');
    Route::get('settings/prompts/list', [PromptController::class, 'index'])->name('settings.prompts.list');
    Route::post('settings/prompts', [PromptController::class, 'store'])->name('settings.prompts.store');
    Route::patch('settings/prompts/{external_id}', [PromptController::class, 'update'])->name('settings.prompts.update');
    Route::delete('settings/prompts/{external_id}', [PromptController::class, 'destroy'])->name('settings.prompts.destroy');

    Route::get('settings/usage', SettingsUsageController::class)->name('settings.usage');

    // Governance — the tool-call audit log (Governed MCP Gateway). Read-only;
    // gated by the new account.governance team permission.
    Route::middleware('team.permission:account.governance')->group(function (): void {
        Route::get('settings/governance', GovernanceAuditController::class)->name('settings.governance');
    });

    // Workspace-level chat members (chatters) — list, fetch the agents one
    // member can access (toggle attach/detach from the detail sheet), send a
    // password-reset link, or remove a chatter outright. All gated by
    // `account.members.manage`.
    Route::middleware('team.permission:account.members.manage')->group(function (): void {
        Route::get('settings/members', [SettingsMemberController::class, 'index'])->name('settings.members.index');
        Route::patch('settings/members/{chatter}', [SettingsMemberController::class, 'update'])
            ->name('settings.members.update');
        Route::post('settings/members/{chatter}/reset-password', [SettingsMemberController::class, 'resetPassword'])
            ->name('settings.members.reset-password');
        Route::delete('settings/members/{chatter}', [SettingsMemberController::class, 'destroy'])
            ->name('settings.members.destroy');

        // Per-chatter agent grants — same shape as the Connectors/Functions
        // agent toggles, consumed by useAgentAttachments('member').
        Route::get('settings/members/{chatter}/agents', [SettingsMemberController::class, 'listAgents'])
            ->name('settings.members.agents.index');
        Route::post('settings/members/{chatter}/agents/{agent}', [SettingsMemberController::class, 'attachAgent'])
            ->name('settings.members.agents.attach');
        Route::delete('settings/members/{chatter}/agents/{agent}', [SettingsMemberController::class, 'detachAgent'])
            ->name('settings.members.agents.detach');
    });

    // Date & time preference — its form lives on the Profile page (merged from
    // the former standalone Preferences page), so only the write endpoint remains.
    Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('settings.preferences.update');

    // Team switching and list (allowed for staff)
    Route::post('my/teams/{team}/switch', TeamSwitchController::class)->name('teams.switch');
    Route::get('my/teams/list', TeamListController::class)->name('my.teams.list');

    // Teams management (denied for staff)
    Route::middleware(DenyIfEmployee::class)->group(function (): void {
        Route::get('my/teams', TeamsHomeController::class)->name('teams.index');
        Route::post('my/teams', TeamsStoreController::class)->name('teams.store');

        Route::get('my/teams/{team}', TeamShowController::class)->name('teams.show');
        Route::put('my/teams/{team}', TeamUpdateController::class)->name('teams.update');
        Route::delete('my/teams/{team}', TeamDeleteController::class)->name('teams.destroy');
        Route::post('my/teams/{team}/leave', TeamLeaveController::class)->name('teams.leave');

        // Team members
        Route::post('my/teams/{team}/invite', TeamInviteMemberController::class)->name('teams.invite');
        Route::delete('my/teams/{team}/invitations/{invitationId}', TeamCancelInvitationController::class)->name('teams.cancel-invitation');
        Route::put('my/teams/{team}/members/{member}', TeamUpdateMemberRoleController::class)->name('teams.update-member');
        Route::delete('my/teams/{team}/members/{member}', TeamRemoveMemberController::class)->name('teams.remove-member');

        // Team roles
        Route::get('my/teams/{team}/roles/create', TeamRoleCreateShowController::class)->name('teams.roles.create');
        Route::post('my/teams/{team}/roles', TeamRoleStoreController::class)->name('teams.create-role');
        Route::get('my/teams/{team}/roles/{roleId}/edit', TeamRoleEditShowController::class)->name('teams.roles.edit');
        Route::put('my/teams/{team}/roles/{roleId}', TeamRoleUpdateController::class)->name('teams.update-role');
        Route::delete('my/teams/{team}/roles/{roleId}', TeamRoleDeleteController::class)->name('teams.delete-role');
    });
});
