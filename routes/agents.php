<?php

use App\Http\Controllers\AgentDeleteSettingsController;
use App\Http\Controllers\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\Agents\AgentCreateController;
use App\Http\Controllers\Agents\Agents\AgentDeleteController;
use App\Http\Controllers\Agents\Agents\AgentsIndexController;
use App\Http\Controllers\Agents\Agents\AgentsListController;
use App\Http\Controllers\Agents\Agents\AgentStoreController;
use App\Http\Controllers\Agents\Agents\AgentUpdateActivityController;
use App\Http\Controllers\Agents\Agents\AgentUpdateController;
use App\Http\Controllers\Agents\Appearance\AgentAppearanceController;
use App\Http\Controllers\Agents\Files\AgentKnowledgeController;
use App\Http\Controllers\Agents\History\AgentHistoryController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileDeleteController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Models\AgentModelsListController;
use App\Http\Controllers\Agents\Models\AgentModelsShowController;
use App\Http\Controllers\Agents\Models\AgentModelsStoreSettings;
use App\Http\Controllers\Agents\Models\AgentModelsToggleController;
use App\Http\Controllers\Agents\Playground\AgentPlaygroundConversationCreateController;
use App\Http\Controllers\Agents\Playground\AgentPlaygroundShowController;
use App\Http\Controllers\Agents\Playground\AgentPlaygroundStreamController;
use App\Http\Controllers\Agents\Settings\AgentShowSettingsController;
use App\Http\Controllers\Agents\Settings\AgentUpdateSettingsController;
use App\Http\Controllers\Agents\Starters\StarterDeleteController;
use App\Http\Controllers\Agents\Starters\StartersShowController;
use App\Http\Controllers\Agents\Starters\StartersStoreController;
use App\Http\Controllers\Agents\Stats\AgentStatsController;
use App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController;
use App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController;
use App\Http\Controllers\Agents\Team\AgentMembersDeleteController;
use App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController;
use App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController;
use App\Http\Controllers\Agents\Team\AgentMembersListController;
use App\Http\Controllers\Agents\Team\AgentTeamController;
use App\Http\Controllers\GenerateRandomSubDomainController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    //    Route::resource('agents', AgentsIndexController::class);

    Route::get('agents', AgentsIndexController::class)->name('agents.index');
    Route::get('agents/list', AgentsListController::class)->name('agents.list');

    Route::post('agents', AgentStoreController::class)
        ->name('agents.store');

    Route::get('agents/create', AgentCreateController::class)->name('agents.create');

    Route::prefix('agents/{agent}')->name('agents.')->group(function () {
        Route::get('stats', AgentStatsController::class)->name('stats');
        Route::patch('/', AgentUpdateController::class)->name('update');

        // Playground routes
        Route::post('playground/conversations/create', AgentPlaygroundConversationCreateController::class)->name('playground.conversations.create');
        Route::post('playground/stream', AgentPlaygroundStreamController::class)->name('playground.stream');

        Route::get('settings', AgentShowSettingsController::class)->name('settings');

        // Knowledge
        Route::get('knowledge', AgentKnowledgeController::class)->name('knowledge');
        Route::get('knowledge/files', AgentKnowledgeFilesController::class)->name('knowledge.files');
        Route::post('knowledge/files/upload', AgentKnowledgeFileUploadController::class)->name('knowledge.files.upload');
        Route::delete('knowledge/files/{file}', AgentKnowledgeFileDeleteController::class)->name('knowledge.files.delete');
        Route::delete('knowledge/uploaded-files', AgentKnowledgeUploadedFilesDeleteController::class)->name('knowledge.uploaded-files');
        Route::post('knowledge/files/process', AgentKnowledgeFilesProcessController::class)->name('knowledge.files.process');

        // Starters
        Route::get('starters', StartersShowController::class)->name('starters');
        Route::post('starters', StartersStoreController::class)->name('starters.store');
        Route::delete('starters/{id}', StarterDeleteController::class)->name('starters.delete');

        Route::get('history', AgentHistoryController::class)->name('history');

        Route::get('appearance', AgentAppearanceController::class)->name('appearance');

        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');

        // Settings
        Route::post('settings/{group}', AgentUpdateSettingsController::class)->name('settings.update');
        Route::delete('settings/{group}', AgentDeleteSettingsController::class)->name('settings.delete');

        // Models
        Route::get('/models/list', AgentModelsListController::class)->name('models.list');
        Route::get('/models', AgentModelsShowController::class)->name('models');
        Route::patch('/models/settings', AgentModelsStoreSettings::class)->name('models.settings.update');
        Route::patch('/models/toggle', AgentModelsToggleController::class)->name('models.toggle');

        // Members
        Route::get('team', AgentTeamController::class)->name('team');
        Route::post('team/invite', AgentMemberInvitationSendController::class)->name('team.invite');
        Route::get('team/list', AgentMembersListController::class)->name('team.list');
        Route::delete('team/member', AgentMembersDeleteController::class)->name('team.member.delete');
        Route::post('team/member/grant', AgentMembersAccessGrantController::class)->name('team.member.grant');

        // Delete Agent
        Route::delete('/', AgentDeleteController::class)->name('delete');

        // Playground
        Route::get('playground', AgentPlaygroundShowController::class)->name('playground');

        Route::post('playground/conversations/create', AgentPlaygroundConversationCreateController::class)->name('playground.conversations.create');
    });

    Route::post('agents/knowledge/files/analyze', AgentKnowledgeFilesAnalyzeController::class)->name('agents.knowledge.files.analyze');

    Route::get('generate-random-subdomain', GenerateRandomSubDomainController::class)->name('generate-random-subdomain');
});

Route::get('agents/{agent}/invitations/{token}', AgentMembersInvitationCreateAccountController::class)
    ->name('agents.invitations');

Route::post('agents/{agent}/invitations/{token}/accept', AgentMembersInvitationAcceptController::class)
    ->name('agents.invitations.accept');
