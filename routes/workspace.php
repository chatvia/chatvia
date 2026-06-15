<?php

use App\Http\Controllers\Settings\Account\ApiKeyController;
use App\Http\Controllers\Settings\Chatter\SettingsChatterController;
use App\Http\Controllers\Settings\Connector\Catalog\ConnectController;
use App\Http\Controllers\Settings\Connector\Catalog\ConnectorController;
use App\Http\Controllers\Settings\Connector\Manage\ConnectorAgentController;
use App\Http\Controllers\Settings\Connector\Manage\ConnectorToolController;
use App\Http\Controllers\Settings\Connector\Manage\SetConnectorToolPermissionController;
use App\Http\Controllers\Settings\Connector\Manage\SetConnectorToolsScopePermissionController;
use App\Http\Controllers\Settings\Connector\Manage\ToggleConnectorController;
use App\Http\Controllers\Settings\Connector\Provisioning\CustomAddController;
use App\Http\Controllers\Settings\Connector\Provisioning\OAuthStartController;
use App\Http\Controllers\Settings\Connector\Provisioning\RefreshConnectorController;
use App\Http\Controllers\Settings\Connector\Provisioning\SalesforceConnectController;
use App\Http\Controllers\Settings\Function\FunctionAgentController;
use App\Http\Controllers\Settings\Function\FunctionController;
use App\Http\Controllers\Settings\Function\ToggleFunctionController;
use App\Http\Controllers\Settings\Governance\GovernanceAuditController;
use App\Http\Controllers\Settings\Prompt\PromptController;
use App\Http\Controllers\Settings\Security\SecurityAuditController;
use App\Http\Controllers\Settings\Security\SecurityPolicyController;
use App\Http\Controllers\Team\Crud\TeamDeleteController;
use App\Http\Controllers\Team\Crud\TeamLeaveController;
use App\Http\Controllers\Team\Crud\TeamsHomeController;
use App\Http\Controllers\Team\Crud\TeamShowController;
use App\Http\Controllers\Team\Crud\TeamsStoreController;
use App\Http\Controllers\Team\Crud\TeamUpdateController;
use App\Http\Controllers\Team\Member\RemoveTeammateFromWorkspaceController;
use App\Http\Controllers\Team\Member\TeamCancelInvitationController;
use App\Http\Controllers\Team\Member\TeamInviteMemberController;
use App\Http\Controllers\Team\Member\TeamRemoveMemberController;
use App\Http\Controllers\Team\Member\TeamResendInvitationController;
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

// Workspace administration — integrations, library, people, governance and
// teams. Account-personal pages live in routes/account.php. Per-section access
// is gated by the existing `account.*` team permissions.
Route::middleware(['auth', 'can.access.dashboard'])->group(function (): void {
    // Integrations — account-level Connectors, Functions and API keys. The
    // Connectors page is the same shape as the per-agent connectors page but at
    // the user level (featured catalog + custom MCP servers, OAuth/token flows).
    Route::prefix('my/workspace/integrations')->name('integrations.')->group(function (): void {
        // Each section is gated by its `account.*` team permission as route
        // middleware (was an inline `abort_unless(has_team_permission(...), 404)`
        // in every controller method). The `,404` keeps the "hide the section's
        // existence" behaviour: an unauthorized teammate gets 404, not 403.
        Route::middleware('team.permission:account.connectors,404')->group(function (): void {
            Route::get('connectors', [ConnectorController::class, 'edit'])->name('connectors.edit');
            Route::get('connectors/data', [ConnectorController::class, 'index'])->name('connectors.data');
            Route::post('connectors/{slug}/connect', ConnectController::class)->name('connectors.connect');
            Route::get('connectors/{slug}/oauth/start', OAuthStartController::class)->name('connectors.oauth.start');
            Route::post('connectors/salesforce/connect', SalesforceConnectController::class)->name('connectors.salesforce.connect');
            Route::post('connectors/custom/add', CustomAddController::class)->name('connectors.custom.add');
            Route::post('connectors/{slug}/enable', ToggleConnectorController::class)->name('connectors.enable');
            Route::delete('connectors/{slug}', [ConnectorController::class, 'destroy'])->name('connectors.destroy');
            Route::post('connectors/{slug}/refresh', RefreshConnectorController::class)->name('connectors.refresh');
            Route::get('connectors/{slug}/tools', ConnectorToolController::class)->name('connectors.tools');

            // Per-tool permission (allow / approval / blocked) — the tri-state shown
            // in the Connectors UI.
            Route::put('connectors/{slug}/tools/{tool}/permission', SetConnectorToolPermissionController::class)
                ->whereNumber('tool')
                ->name('connectors.tools.permission');

            // Bulk: set the same permission on every tool in one scope (read /
            // write) at once — the section "apply to all" dropdown in the UI.
            Route::put('connectors/{slug}/tools/permissions', SetConnectorToolsScopePermissionController::class)
                ->name('connectors.tools.scope-permission');

            // Manage which agents use a connector (from the Connectors sheet).
            Route::get('connectors/{slug}/agents', [ConnectorAgentController::class, 'index'])->name('connectors.agents.index');
            Route::post('connectors/{slug}/agents/{agent}', [ConnectorAgentController::class, 'store'])->name('connectors.agents.attach');
            Route::delete('connectors/{slug}/agents/{agent}', [ConnectorAgentController::class, 'destroy'])->name('connectors.agents.detach');
        });

        Route::middleware('team.permission:account.functions,404')->group(function (): void {
            Route::get('functions', [FunctionController::class, 'edit'])->name('functions.edit');
            Route::post('functions', [FunctionController::class, 'store'])->name('functions.store');
            Route::patch('functions/{external_id}', [FunctionController::class, 'update'])->name('functions.update');
            Route::post('functions/{external_id}/toggle', ToggleFunctionController::class)->name('functions.toggle');
            Route::delete('functions/{external_id}', [FunctionController::class, 'destroy'])->name('functions.destroy');

            // Manage which agents use a function (from the Functions sheet).
            Route::get('functions/{external_id}/agents', [FunctionAgentController::class, 'index'])->name('functions.agents.index');
            Route::post('functions/{external_id}/agents/{agent}', [FunctionAgentController::class, 'store'])->name('functions.agents.attach');
            Route::delete('functions/{external_id}/agents/{agent}', [FunctionAgentController::class, 'destroy'])->name('functions.agents.detach');
        });

        Route::middleware('team.permission:account.api_keys,404')->group(function (): void {
            Route::get('api-keys', [ApiKeyController::class, 'edit'])->name('api-keys.edit');
            Route::post('api-keys', [ApiKeyController::class, 'store'])->name('api-keys.store');
            Route::delete('api-keys/{tokenId}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        });
    });

    // Library — account-level reusable prompts (Prompt Manager). Authored here,
    // attached to agents from each agent's Persona page via `agent_prompt_id`.
    // Gated by `account.prompts` (404 to hide existence), formerly inline.
    Route::middleware('team.permission:account.prompts,404')->prefix('my/workspace/library')->name('library.')->group(function (): void {
        Route::get('prompts', [PromptController::class, 'edit'])->name('prompts.edit');
        Route::get('prompts/list', [PromptController::class, 'index'])->name('prompts.list');
        Route::post('prompts', [PromptController::class, 'store'])->name('prompts.store');
        Route::patch('prompts/{external_id}', [PromptController::class, 'update'])->name('prompts.update');
        Route::delete('prompts/{external_id}', [PromptController::class, 'destroy'])->name('prompts.destroy');
    });

    // People — workspace-level chat members (chatters): list, fetch the agents one
    // member can access (toggle attach/detach from the detail sheet), send a
    // password-reset link, or remove a chatter outright. Gated by
    // `account.chatters.manage`.
    Route::middleware('team.permission:account.chatters.manage')->prefix('my/workspace/people')->name('people.')->group(function (): void {
        Route::get('chatters', [SettingsChatterController::class, 'index'])->name('chatters.index');
        Route::post('chatters/invite', [SettingsChatterController::class, 'invite'])->name('chatters.invite');
        Route::patch('chatters/{chatter}', [SettingsChatterController::class, 'update'])->name('chatters.update');
        Route::post('chatters/{chatter}/reset-password', [SettingsChatterController::class, 'resetPassword'])->name('chatters.reset-password');
        Route::delete('chatters/{chatter}', [SettingsChatterController::class, 'destroy'])->name('chatters.destroy');

        // Per-chatter agent grants — same shape as the Connectors/Functions agent
        // toggles, consumed by useAgentAttachments('chatter').
        Route::get('chatters/{chatter}/agents', [SettingsChatterController::class, 'listAgents'])->name('chatters.agents.index');
        Route::post('chatters/{chatter}/agents/{agent}', [SettingsChatterController::class, 'attachAgent'])->name('chatters.agents.attach');
        Route::delete('chatters/{chatter}/agents/{agent}', [SettingsChatterController::class, 'detachAgent'])->name('chatters.agents.detach');
    });

    // Governance — the tool-call audit log (Governed MCP Gateway). Read-only;
    // gated by the `account.governance` team permission.
    Route::middleware('team.permission:account.governance')->group(function (): void {
        Route::get('my/workspace/governance', GovernanceAuditController::class)->name('governance.audit');
    });

    // Security & Compliance — workspace-wide network access control (IP
    // allowlist + geo). Viewable by anyone with `account.security` (non-Enterprise
    // sees an upsell teaser); saving additionally requires the `security`
    // entitlement, so the gate can't be configured without an entitled plan.
    Route::middleware('team.permission:account.security')->group(function (): void {
        Route::get('my/workspace/security', [SecurityPolicyController::class, 'edit'])->name('security.edit');
        Route::put('my/workspace/security', [SecurityPolicyController::class, 'update'])
            ->middleware('features:security')->name('security.update');

        // Read-only security audit log viewer (non-entitled sees the upsell).
        Route::get('my/workspace/security/audit', SecurityAuditController::class)->name('security.audit');
    });

    // Teams — workspace teammates and roles. URLs and names are unchanged
    // (`my/teams/*`, `teams.*`); they live here because they are workspace-level.
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
        Route::post('my/teams/{team}/invitations/{invitationId}/resend', TeamResendInvitationController::class)->name('teams.resend-invitation');
        Route::put('my/teams/{team}/members/{member}', TeamUpdateMemberRoleController::class)->name('teams.update-member');
        Route::delete('my/teams/{team}/members/{member}', TeamRemoveMemberController::class)->name('teams.remove-member');

        // Permanently delete a teammate's account + all their data — distinct
        // from remove-member above, which only detaches the team pivot.
        // Workspace-level (not team-scoped); DenyIfEmployee already limits it to owners.
        Route::delete('my/workspace/teammates/{member}', RemoveTeammateFromWorkspaceController::class)->name('teammates.remove');

        // Team roles
        Route::get('my/teams/{team}/roles/create', TeamRoleCreateShowController::class)->name('teams.roles.create');
        Route::post('my/teams/{team}/roles', TeamRoleStoreController::class)->name('teams.create-role');
        Route::get('my/teams/{team}/roles/{roleId}/edit', TeamRoleEditShowController::class)->name('teams.roles.edit');
        Route::put('my/teams/{team}/roles/{roleId}', TeamRoleUpdateController::class)->name('teams.update-role');
        Route::delete('my/teams/{team}/roles/{roleId}', TeamRoleDeleteController::class)->name('teams.delete-role');
    });
});
