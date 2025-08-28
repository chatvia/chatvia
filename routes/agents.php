<?php

use App\Http\Controllers\Agents\Actions\AgentActionsController;
use App\Http\Controllers\Agents\Agents\AgentCreateController;
use App\Http\Controllers\Agents\Agents\AgentDeleteController;
use App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController;
use App\Http\Controllers\Agents\Agents\AgentFileSummaryController;
use App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\Agents\AgentPreviewController;
use App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agents\Agents\AgentsIndexController;
use App\Http\Controllers\Agents\Agents\AgentsListController;
use App\Http\Controllers\Agents\Agents\AgentStoreController;
use App\Http\Controllers\Agents\Agents\AgentUpdateActivityController;
use App\Http\Controllers\Agents\Agents\AgentUpdateController;
use App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController;
use App\Http\Controllers\Agents\Appearance\AgentAppearanceController;
use App\Http\Controllers\Agents\Embed\EmbedController;
use App\Http\Controllers\Agents\Files\AgentKnowledgeController;
use App\Http\Controllers\Agents\History\AgentHistoryController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileDeleteController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Knowledge\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Knowledge\Crawler\AgentKnowledgeCrawlerController;
use App\Http\Controllers\Agents\Knowledge\Crawler\AgentKnowledgeCrawlerWebsiteLinksController;
use App\Http\Controllers\Agents\Knowledge\Crawler\AgentKnowledgeCrawlerWebsiteTrainController;
use App\Http\Controllers\Agents\Knowledge\Crawler\AgentKnowledgeWebsiteDeleteController;
use App\Http\Controllers\Agents\Knowledge\Crawler\AgentKnowledgeWebsitesListController;
use App\Http\Controllers\Agents\Models\AgentModelsListController;
use App\Http\Controllers\Agents\Models\AgentModelsShowController;
use App\Http\Controllers\Agents\Models\AgentModelsStoreSettings;
use App\Http\Controllers\Agents\Models\AgentModelsToggleController;
use App\Http\Controllers\Agents\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agents\Security\AgentSecurityController;
use App\Http\Controllers\Agents\Security\AgentSecurityUpdateController;
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
use App\Http\Controllers\Agents\Widget\WidgetController;
use App\Http\Controllers\Agents\Widget\WidgetPreviewController;
use App\Http\Controllers\Agents\Workflow\AgentWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    //    Route::resource('agents', AgentsIndexController::class);

    Route::get('agents', AgentsIndexController::class)->name('agents.index');
    Route::get('agents/list', AgentsListController::class)->name('agents.list');

    Route::post('agents', AgentStoreController::class)->name('agents.store');
    Route::get('agents/create', AgentCreateController::class)->name('agents.create');

    Route::prefix('agents/{agent}')->name('agents.')->group(function () {
        Route::get('stats', AgentStatsController::class)->name('stats');
        Route::patch('/', AgentUpdateController::class)->name('update');
        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('preview', AgentPreviewController::class)->name('preview');

        Route::get('settings', AgentShowPersonaController::class)->name('settings');
        Route::get('security', AgentSecurityController::class)->name('security');

        // Knowledge
        Route::get('knowledge', AgentKnowledgeController::class)->name('knowledge');
        Route::get('knowledge/files', AgentKnowledgeFilesController::class)->name('knowledge.files');
        Route::post('knowledge/files/upload', AgentKnowledgeFileUploadController::class)->name('knowledge.files.upload');
        Route::delete('knowledge/files/{file}', AgentKnowledgeFileDeleteController::class)->name('knowledge.files.delete');
        Route::delete('knowledge/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->name('knowledge.uploaded-files');
        Route::post('knowledge/files/process', AgentKnowledgeFilesProcessController::class)->name('knowledge.files.process');
        Route::get('knowledge/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::post('knowledge/website', AgentKnowledgeCrawlerController::class)->name('knowledge.website.crawl');
        Route::get('knowledge/websites/list', AgentKnowledgeWebsitesListController::class)->name('knowledge.websites.list');
        Route::delete('knowledge/websites/{website}', AgentKnowledgeWebsiteDeleteController::class)->name('knowledge.websites.delete');
        Route::get('knowledge/websites/{website}/links', AgentKnowledgeCrawlerWebsiteLinksController::class)->name('knowledge.websites.links');
        Route::post('knowledge/website/{website}/train', AgentKnowledgeCrawlerWebsiteTrainController::class)->name('knowledge.website.train');

        // Starters
        Route::get('starters', StartersShowController::class)->name('starters');
        Route::post('starters', StartersStoreController::class)->name('starters.store');
        Route::delete('starters/{id}', StarterDeleteController::class)->name('starters.delete');

        Route::get('history', AgentHistoryController::class)->name('history');

        Route::get('appearance', AgentAppearanceController::class)->name('appearance');

        Route::get('widget', WidgetController::class)->name('widget');
        Route::get('embed', EmbedController::class)->name('embed');

        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');
        Route::patch('security', AgentSecurityUpdateController::class)->name('security.update');

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

        // Workflow
        Route::get('workflow', AgentWorkflowController::class)->name('workflow');

        // Actions
        Route::get('actions', AgentActionsController::class)->name('actions');

        // Delete Agent
        Route::delete('/', AgentDeleteController::class)->name('delete');

    });

    Route::post('agents/knowledge/files/analyze', AgentKnowledgeFilesAnalyzeController::class)->name('agents.knowledge.files.analyze');

    Route::get('generate-random-subdomain', AgentsGenerateRandomSubDomainController::class)->name('generate-random-subdomain');
});

Route::get('agents/{agent}/invitations/{token}', AgentMembersInvitationCreateAccountController::class)
    ->name('agents.invitations');

Route::post('agents/{agent}/invitations/{token}/accept', AgentMembersInvitationAcceptController::class)
    ->name('agents.invitations.accept');
