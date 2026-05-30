<?php

use App\Http\Controllers\Settings\Account\ApiKeysController;
use App\Http\Controllers\Settings\Account\PasswordController;
use App\Http\Controllers\Settings\Account\ProfileController;
use App\Http\Controllers\Settings\Connectors\ConnectController;
use App\Http\Controllers\Settings\Connectors\ConnectorAgentsController;
use App\Http\Controllers\Settings\Connectors\ConnectorsController;
use App\Http\Controllers\Settings\Connectors\ConnectorToolsController;
use App\Http\Controllers\Settings\Connectors\CustomAddController;
use App\Http\Controllers\Settings\Connectors\OAuthStartController;
use App\Http\Controllers\Settings\Connectors\RefreshConnectorController;
use App\Http\Controllers\Settings\Connectors\ToggleConnectorController;
use App\Http\Controllers\Settings\Connectors\ToggleConnectorToolController;
use App\Http\Controllers\Settings\Functions\FunctionAgentsController;
use App\Http\Controllers\Settings\Members\SettingsMembersController;
use App\Http\Controllers\Settings\Functions\FunctionsController;
use App\Http\Controllers\Settings\Functions\ToggleFunctionController;
use App\Http\Controllers\Settings\Prompts\PromptsController;
use App\Http\Controllers\Settings\Templates\AgentTemplatesController;
use App\Http\Controllers\Settings\Templates\AgentTemplatesListController;
use App\Http\Controllers\Settings\Usage\SettingsUsageController;
use App\Http\Controllers\Teams\Crud\TeamDeleteController;
use App\Http\Controllers\Teams\Crud\TeamLeaveController;
use App\Http\Controllers\Teams\Crud\TeamShowController;
use App\Http\Controllers\Teams\Crud\TeamsHomeController;
use App\Http\Controllers\Teams\Crud\TeamsStoreController;
use App\Http\Controllers\Teams\Crud\TeamUpdateController;
use App\Http\Controllers\Teams\Members\TeamCancelInvitationController;
use App\Http\Controllers\Teams\Members\TeamInviteMemberController;
use App\Http\Controllers\Teams\Members\TeamRemoveMemberController;
use App\Http\Controllers\Teams\Members\TeamUpdateMemberRoleController;
use App\Http\Controllers\Teams\Roles\TeamRoleCreateShowController;
use App\Http\Controllers\Teams\Roles\TeamRoleDeleteController;
use App\Http\Controllers\Teams\Roles\TeamRoleEditShowController;
use App\Http\Controllers\Teams\Roles\TeamRoleStoreController;
use App\Http\Controllers\Teams\Roles\TeamRoleUpdateController;
use App\Http\Controllers\Teams\Switching\TeamListController;
use App\Http\Controllers\Teams\Switching\TeamSwitchController;
use App\Http\Middleware\DenyIfEmployee;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'can.access.dashboard'])->group(function (): void {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/api-keys', [ApiKeysController::class, 'edit'])->name('api-keys.edit');
    Route::post('settings/api-keys', [ApiKeysController::class, 'store'])->name('api-keys.store');
    Route::delete('settings/api-keys/{tokenId}', [ApiKeysController::class, 'destroy'])->name('api-keys.destroy');

    // Account-level Connectors — the same shape as the per-agent connectors
    // page, but at the user level. Featured catalog connectors + custom MCP
    // servers, with the existing OAuth/token flows pointed at the user.
    Route::get('settings/connectors', [ConnectorsController::class, 'edit'])->name('settings.connectors.edit');
    Route::get('settings/connectors/data', [ConnectorsController::class, 'index'])->name('settings.connectors.data');
    Route::post('settings/connectors/{slug}/connect', ConnectController::class)->name('settings.connectors.connect');
    Route::get('settings/connectors/{slug}/oauth/start', OAuthStartController::class)->name('settings.connectors.oauth.start');
    Route::post('settings/connectors/custom/add', CustomAddController::class)->name('settings.connectors.custom.add');
    Route::post('settings/connectors/{slug}/enable', ToggleConnectorController::class)->name('settings.connectors.enable');
    Route::delete('settings/connectors/{slug}', [ConnectorsController::class, 'destroy'])->name('settings.connectors.destroy');
    Route::post('settings/connectors/{slug}/refresh', RefreshConnectorController::class)->name('settings.connectors.refresh');
    Route::get('settings/connectors/{slug}/tools', ConnectorToolsController::class)->name('settings.connectors.tools');
    Route::post('settings/connectors/{slug}/tools/{tool}/toggle', ToggleConnectorToolController::class)
        ->whereNumber('tool')
        ->name('settings.connectors.tools.toggle');

    // Manage which agents use a connector (from the Connectors sheet).
    Route::get('settings/connectors/{slug}/agents', [ConnectorAgentsController::class, 'index'])->name('settings.connectors.agents.index');
    Route::post('settings/connectors/{slug}/agents/{agent}', [ConnectorAgentsController::class, 'store'])->name('settings.connectors.agents.store');
    Route::delete('settings/connectors/{slug}/agents/{agent}', [ConnectorAgentsController::class, 'destroy'])->name('settings.connectors.agents.destroy');

    Route::get('settings/functions', [FunctionsController::class, 'edit'])->name('settings.functions.edit');
    Route::post('settings/functions', [FunctionsController::class, 'store'])->name('settings.functions.store');
    Route::patch('settings/functions/{external_id}', [FunctionsController::class, 'update'])->name('settings.functions.update');
    Route::post('settings/functions/{external_id}/toggle', ToggleFunctionController::class)->name('settings.functions.toggle');
    Route::delete('settings/functions/{external_id}', [FunctionsController::class, 'destroy'])->name('settings.functions.destroy');

    // Manage which agents use a function (from the Functions sheet).
    Route::get('settings/functions/{external_id}/agents', [FunctionAgentsController::class, 'index'])->name('settings.functions.agents.index');
    Route::post('settings/functions/{external_id}/agents/{agent}', [FunctionAgentsController::class, 'store'])->name('settings.functions.agents.store');
    Route::delete('settings/functions/{external_id}/agents/{agent}', [FunctionAgentsController::class, 'destroy'])->name('settings.functions.agents.destroy');

    // Account-level reusable prompts (Prompt Manager). Authored here, attached
    // to agents from each agent's Persona page via `agent_prompt_id`.
    Route::get('settings/prompts', [PromptsController::class, 'edit'])->name('settings.prompts.edit');
    Route::get('settings/prompts/list', [PromptsController::class, 'index'])->name('settings.prompts.list');
    Route::post('settings/prompts', [PromptsController::class, 'store'])->name('settings.prompts.store');
    Route::patch('settings/prompts/{external_id}', [PromptsController::class, 'update'])->name('settings.prompts.update');
    Route::delete('settings/prompts/{external_id}', [PromptsController::class, 'destroy'])->name('settings.prompts.destroy');

    // Account-level reusable agent templates (Settings → Templates). A template
    // bundles appearance/widget/models/starters/prompt; while attached to an
    // agent the agent reads its config from the template at runtime.
    Route::get('settings/templates', [AgentTemplatesController::class, 'index'])->name('settings.templates.index');
    Route::get('settings/templates/list', AgentTemplatesListController::class)->name('settings.templates.list');
    Route::get('settings/templates/create', [AgentTemplatesController::class, 'create'])->name('settings.templates.create');
    Route::post('settings/templates', [AgentTemplatesController::class, 'store'])->name('settings.templates.store');
    Route::get('settings/templates/{external_id}/edit', [AgentTemplatesController::class, 'edit'])->name('settings.templates.edit');
    Route::put('settings/templates/{external_id}', [AgentTemplatesController::class, 'update'])->name('settings.templates.update');
    Route::delete('settings/templates/{external_id}', [AgentTemplatesController::class, 'destroy'])->name('settings.templates.destroy');

    Route::get('settings/usage', SettingsUsageController::class)->name('settings.usage');

    // Workspace-level chat members (chatters) — list, fetch the agents one
    // member can access (toggle attach/detach from the detail sheet), send a
    // password-reset link, or remove a chatter outright. All gated by
    // `account.members.manage`.
    Route::get('settings/members', [SettingsMembersController::class, 'index'])->name('settings.members.index');
    Route::post('settings/members/{chatter}/reset-password', [SettingsMembersController::class, 'resetPassword'])
        ->name('settings.members.reset-password');
    Route::delete('settings/members/{chatter}', [SettingsMembersController::class, 'destroy'])
        ->name('settings.members.destroy');

    // Per-chatter agent grants — same shape as the Connectors/Functions
    // agent toggles, consumed by useAgentAttachments('member').
    Route::get('settings/members/{chatter}/agents', [SettingsMembersController::class, 'listAgents'])
        ->name('settings.members.agents.index');
    Route::post('settings/members/{chatter}/agents/{agent}', [SettingsMembersController::class, 'attachAgent'])
        ->name('settings.members.agents.attach');
    Route::delete('settings/members/{chatter}/agents/{agent}', [SettingsMembersController::class, 'detachAgent'])
        ->name('settings.members.agents.detach');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

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
