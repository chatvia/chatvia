<?php

use App\Http\Controllers\Agents\Actions\AgentActionsController;
use App\Http\Controllers\Agents\Agents\AgentDeleteController;
use App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController;
use App\Http\Controllers\Agents\Agents\AgentFileSummaryController;
use App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\Agents\AgentPreviewController;
use App\Http\Controllers\Agents\Agents\AgentRenameController;
use App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agents\Agents\AgentsIndexController;
use App\Http\Controllers\Agents\Agents\AgentsListController;
use App\Http\Controllers\Agents\Agents\AgentStoreController;
use App\Http\Controllers\Agents\Agents\AgentUpdateController;
use App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController;
use App\Http\Controllers\Agents\Appearance\AgentAppearanceController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogController;
use App\Http\Controllers\Agents\Embed\EmbedController;
use App\Http\Controllers\Agents\Files\AgentKnowledgeController;
use App\Http\Controllers\Agents\History\AgentHistoryController;
use App\Http\Controllers\Agents\Leads\AgentLeadsController;
use App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController;
use App\Http\Controllers\Agents\Leads\AgentLeadsExportController;
use App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController;
use App\Http\Controllers\Agents\Models\AgentModelsListController;
use App\Http\Controllers\Agents\Models\AgentModelsShowController;
use App\Http\Controllers\Agents\Models\AgentModelsStoreSettings;
use App\Http\Controllers\Agents\Models\AgentModelsToggleController;
use App\Http\Controllers\Agents\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agents\Security\AgentSecurityController;
use App\Http\Controllers\Agents\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFileDeleteController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinkDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteLinksController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeCrawlerWebsiteTrainController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsiteDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentKnowledgeWebsitesListController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController;
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

    Route::prefix('agents/{agent}')->name('agents.')->group(function () {
        Route::get('stats', AgentStatsController::class)->name('stats');
        Route::patch('/', AgentUpdateController::class)->name('update');
        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('preview', AgentPreviewController::class)->name('preview');

        Route::get('persona', AgentShowPersonaController::class)->name('persona');
        Route::get('security', AgentSecurityController::class)->name('security');

        // Knowledge
        Route::get('sources', AgentKnowledgeController::class)->name('sources');
        Route::get('sources/pdf', AgentKnowledgePdfController::class)->name('sources.pdf');
        Route::get('sources/word', AgentKnowledgeWordController::class)->name('sources.word');
        Route::get('sources/web', AgentKnowledgeWebController::class)->name('sources.web');

        Route::get('sources/text', AgentKnowledgeTextController::class)->name('sources.text');
        Route::post('sources/text', AgentKnowledgeTextStoreController::class)->name('sources.text.store');
        Route::get('sources/text/list', AgentKnowledgeTextListController::class)->name('sources.text.index');
        Route::get('sources/text/{id}', AgentKnowledgeTextShowController::class)->name('sources.text.show');

        Route::get('sources/files', AgentKnowledgeFilesController::class)->name('sources.files');
        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->name('sources.files.upload');
        Route::delete('sources/text/{id}', AgentKnowledgeTextDestroyController::class)->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentKnowledgeFileDeleteController::class)->name('sources.files.delete');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::post('sources/website', AgentKnowledgeCrawlerController::class)->name('sources.website.crawl');
        Route::get('sources/websites/list', AgentKnowledgeWebsitesListController::class)->name('sources.websites.list');
        Route::delete('sources/websites/{website}', AgentKnowledgeWebsiteDeleteController::class)->name('sources.websites.delete');
        Route::get('sources/websites/{website}/links', AgentKnowledgeCrawlerWebsiteLinksController::class)->name('sources.websites.links');
        Route::delete('sources/websites/{website}/links/{link}', AgentKnowledgeCrawlerWebsiteLinkDeleteController::class)->name('sources.websites.links.delete');
        Route::post('sources/websites/{website}/links/bulk-delete', AgentKnowledgeCrawlerWebsiteLinksBulkDeleteController::class)->name('sources.websites.links.bulk-delete');
        Route::post('sources/website/{website}/train', AgentKnowledgeCrawlerWebsiteTrainController::class)->name('sources.website.train');

        // Starters
        Route::get('starters', StartersShowController::class)->name('starters');
        Route::post('starters', StartersStoreController::class)->name('starters.store');
        Route::delete('starters/{id}', StarterDeleteController::class)->name('starters.delete');

        Route::get('history', AgentHistoryController::class)->name('history');
        Route::get('leads', AgentLeadsController::class)->name('leads');
        Route::delete('leads', AgentLeadsDeleteController::class)->name('leads.delete');
        Route::patch('leads/settings', AgentLeadsUpdateSettingsController::class)->name('leads.settings');
        Route::post('leads/export', AgentLeadsExportController::class)->name('leads.export');

        Route::get('logs', AgentChatLogController::class)->name('logs');

        Route::get('appearance', AgentAppearanceController::class)->name('appearance');

        Route::get('widget', WidgetController::class)->name('widget');
        Route::get('embed', EmbedController::class)->name('embed');

        //        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');
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

        // Rename Agent
        Route::patch('rename', AgentRenameController::class)->name('rename');

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
