<?php

use App\Http\Controllers\Agents\AgentManagerController;
use App\Http\Controllers\Agents\Agents\AgentDeleteController;
use App\Http\Controllers\Agents\Agents\AgentDeleteSettingsController;
use App\Http\Controllers\Agents\Agents\AgentFileSummaryController;
use App\Http\Controllers\Agents\Agents\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\Agents\AgentPlaygroundController;
use App\Http\Controllers\Agents\Agents\AgentRenameController;
use App\Http\Controllers\Agents\Agents\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agents\Agents\AgentsIndexController;
use App\Http\Controllers\Agents\Agents\AgentsListController;
use App\Http\Controllers\Agents\Agents\AgentSourcesLinkFileController;
use App\Http\Controllers\Agents\Agents\AgentSourcesTrainedListController;
use App\Http\Controllers\Agents\Agents\AgentSourceUnlinkFileController;
use App\Http\Controllers\Agents\Agents\AgentStoreController;
use App\Http\Controllers\Agents\Agents\AgentUpdateController;
use App\Http\Controllers\Agents\Agents\AgentUpdateSettingsController;
use App\Http\Controllers\Agents\Analytics\AgentAnalyticsController;
use App\Http\Controllers\Agents\Analytics\AgentAnalyticsDataController;
use App\Http\Controllers\Agents\Analytics\AgentChartDataController;
use App\Http\Controllers\Agents\Analytics\AgentMetricsController;
use App\Http\Controllers\Agents\Analytics\AgentMetricsDataController;
use App\Http\Controllers\Agents\Analytics\AgentSentimentController;
use App\Http\Controllers\Agents\Analytics\AgentSentimentDataController;
use App\Http\Controllers\Agents\Analytics\AgentSourcesController;
use App\Http\Controllers\Agents\Analytics\AgentSourcesDataController;
use App\Http\Controllers\Agents\Analytics\AgentToneController;
use App\Http\Controllers\Agents\Analytics\AgentToneDataController;
use App\Http\Controllers\Agents\Analytics\AgentTopicsController;
use App\Http\Controllers\Agents\Analytics\AgentTopicsDataController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogConversationPulseController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogConversationsController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogCountriesController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogMessagesController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogReviseAnswerController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogTopicsController;
use App\Http\Controllers\Agents\Embed\EmbedController;
use App\Http\Controllers\Agents\Handoff\HandoffMarkReadController;
use App\Http\Controllers\Agents\Handoff\HandoffMessageController;
use App\Http\Controllers\Agents\Handoff\HandoffPendingListController;
use App\Http\Controllers\Agents\Handoff\HandoffReturnController;
use App\Http\Controllers\Agents\Handoff\HandoffTakeOverController;
use App\Http\Controllers\Agents\Handoff\HandoffTypingController;
use App\Http\Controllers\Agents\Leads\AgentLeadsController;
use App\Http\Controllers\Agents\Leads\AgentLeadsDataController;
use App\Http\Controllers\Agents\Leads\AgentLeadsDeleteController;
use App\Http\Controllers\Agents\Leads\AgentLeadsExportController;
use App\Http\Controllers\Agents\Leads\AgentLeadsUpdateSettingsController;
use App\Http\Controllers\Agents\Models\AgentModelsListController;
use App\Http\Controllers\Agents\Models\AgentModelsStoreSettings;
use App\Http\Controllers\Agents\Models\AgentModelsToggleController;
use App\Http\Controllers\Agents\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agents\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgePdfController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeWordController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeYoutubeController;
use App\Http\Controllers\Agents\Sources\AgentSourcesFilesController;
use App\Http\Controllers\Agents\Sources\Audio\AgentSourceAudioController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerLinkRetrainController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerWebsiteLinkDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerWebsiteLinksBulkDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerWebsiteLinksController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceCrawlerWebsiteTrainController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourcesLinkWebsiteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourcesTrainedWebsitesListController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceWebsiteDeleteController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceWebsitesListController;
use App\Http\Controllers\Agents\Sources\Crawler\AgentSourceWebsiteUnlinkController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaDestroyController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaListController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaShowController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaStoreController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaUnlinkController;
use App\Http\Controllers\Agents\Sources\Qa\AgentKnowledgeQaUpdateController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextDestroyController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextListController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextShowController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextStoreController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextUnlinkController;
use App\Http\Controllers\Agents\Sources\Text\AgentKnowledgeTextUpdateController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesLinkTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesTrainedTextsListController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourceUnlinkTextController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceFileDeleteController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceVideoController;
use App\Http\Controllers\Agents\Sources\Youtube\AgentKnowledgeYoutubeDestroyController;
use App\Http\Controllers\Agents\Sources\Youtube\AgentKnowledgeYoutubeListController;
use App\Http\Controllers\Agents\Sources\Youtube\AgentKnowledgeYoutubeRetrainController;
use App\Http\Controllers\Agents\Sources\Youtube\AgentKnowledgeYoutubeStoreController;
use App\Http\Controllers\Agents\Starters\StarterDeleteController;
use App\Http\Controllers\Agents\Starters\StartersStoreController;
use App\Http\Controllers\Agents\Team\AgentMemberInvitationSendController;
use App\Http\Controllers\Agents\Team\AgentMembersAccessGrantController;
use App\Http\Controllers\Agents\Team\AgentMembersDeleteController;
use App\Http\Controllers\Agents\Team\AgentMembersInvitationAcceptController;
use App\Http\Controllers\Agents\Team\AgentMembersInvitationCreateAccountController;
use App\Http\Controllers\Agents\Team\AgentMembersListController;
use App\Http\Controllers\Agents\Team\AgentTeamController;
use App\Http\Controllers\Agents\Topics\AgentTopicsDeleteController;
use App\Http\Controllers\Agents\Topics\AgentTopicsListController;
use App\Http\Controllers\Agents\Topics\AgentTopicsManageController;
use App\Http\Controllers\Agents\Topics\AgentTopicsStoreController;
use App\Http\Controllers\Agents\Topics\AgentTopicsUpdateController;
use App\Http\Controllers\Agents\Widget\WidgetController;
use App\Http\Controllers\Agents\Widget\WidgetPreviewController;
use App\Http\Controllers\Agents\Workflow\AgentWorkflowController;
use App\Http\Controllers\CountriesListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'user.subscribed'])->prefix('my/')->group(function () {
    //    Route::resource('agents', AgentsIndexController::class);

    Route::get('agents', AgentsIndexController::class)->name('agents.index');
    Route::get('agents/list', AgentsListController::class)->name('agents.list');

    Route::post('agents', AgentStoreController::class)->name('agents.store');

    Route::get('countries/list', CountriesListController::class)->name('countries.list');

    Route::prefix('agents/{agent}')->name('agents.')->middleware('can.access.agent')->group(function () {
        Route::get('manager', AgentManagerController::class)->name('manager');
        Route::get('analytics', AgentAnalyticsController::class)->name('analytics');
        Route::get('analytics/data', AgentAnalyticsDataController::class)->name('analytics.data');
        Route::get('analytics/chart-data', AgentChartDataController::class)->name('analytics.chart-data');
        Route::get('analytics/metrics', AgentMetricsController::class)->name('analytics.metrics');
        Route::get('analytics/metrics/data', AgentMetricsDataController::class)->name('metrics.data');
        Route::get('analytics/sentiment', AgentSentimentController::class)->name('analytics.sentiment');
        Route::get('analytics/sentiment/data', AgentSentimentDataController::class)->name('sentiment.data');
        Route::get('analytics/tone', AgentToneController::class)->name('analytics.tone');
        Route::get('analytics/tone/data', AgentToneDataController::class)->name('tone.data');
        Route::get('analytics/topics', AgentTopicsController::class)->name('analytics.topics');
        Route::get('analytics/topics/data', AgentTopicsDataController::class)->name('topics.data');
        Route::get('analytics/sources', AgentSourcesController::class)->name('analytics.sources');
        Route::get('analytics/sources/data', AgentSourcesDataController::class)->name('analytics.sources.data');

        // Topics Management
        Route::get('topics/manage', AgentTopicsManageController::class)->name('topics.manage');
        Route::get('topics/list', AgentTopicsListController::class)->name('topics.list');
        Route::post('topics', AgentTopicsStoreController::class)->name('topics.store');
        Route::patch('topics/{topic}', AgentTopicsUpdateController::class)->name('topics.update');
        Route::delete('topics/{topic}', AgentTopicsDeleteController::class)->name('topics.delete');
        Route::patch('/', AgentUpdateController::class)->name('update');
        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('playground', AgentPlaygroundController::class)->name('playground');

        Route::get('persona', AgentShowPersonaController::class)->name('persona');

        // Knowledge
        Route::get('sources', AgentKnowledgeSourcesController::class)->name('sources');
        Route::get('sources/stats', AgentKnowledgeSourcesStatsController::class)->name('sources.stats');
        Route::get('sources/pdf', AgentKnowledgePdfController::class)->name('sources.pdf');
        Route::get('sources/word', AgentKnowledgeWordController::class)->name('sources.word');
        Route::get('sources/markdown', AgentKnowledgeMarkdownController::class)->name('sources.markdown');
        Route::get('sources/audio', AgentSourceAudioController::class)->name('sources.audio');
        Route::get('sources/video', AgentSourceVideoController::class)->name('sources.video');
        Route::get('sources/web', AgentKnowledgeWebController::class)->name('sources.web');
        Route::get('sources/youtube', AgentKnowledgeYoutubeController::class)->name('sources.youtube');
        Route::get('sources/youtube/list', AgentKnowledgeYoutubeListController::class)->name('sources.youtube.list');
        Route::post('sources/youtube', AgentKnowledgeYoutubeStoreController::class)->name('sources.youtube.store');
        Route::delete('sources/youtube/{youtube}', AgentKnowledgeYoutubeDestroyController::class)->name('sources.youtube.destroy');
        Route::post('sources/youtube/{youtube}/retrain', AgentKnowledgeYoutubeRetrainController::class)->name('sources.youtube.retrain');

        Route::get('sources/text', AgentKnowledgeTextController::class)->name('sources.text');
        Route::post('sources/text', AgentKnowledgeTextStoreController::class)->name('sources.text.store');
        Route::get('sources/text/list', AgentKnowledgeTextListController::class)->name('sources.text.index');
        Route::get('sources/text/{id}', AgentKnowledgeTextShowController::class)->name('sources.text.show');
        Route::patch('sources/text/{id}', AgentKnowledgeTextUpdateController::class)->name('sources.text.update');
        Route::post('sources/text/{id}/unlink', AgentKnowledgeTextUnlinkController::class)->name('sources.text.unlink');

        Route::get('sources/qa', AgentKnowledgeQaController::class)->name('sources.qa');
        Route::post('sources/qa', AgentKnowledgeQaStoreController::class)->name('sources.qa.store');
        Route::get('sources/qa/list', AgentKnowledgeQaListController::class)->name('sources.qa.index');
        Route::get('sources/qa/{id}', AgentKnowledgeQaShowController::class)->name('sources.qa.show');
        Route::patch('sources/qa/{id}', AgentKnowledgeQaUpdateController::class)->name('sources.qa.update');
        Route::delete('sources/qa/{id}', AgentKnowledgeQaDestroyController::class)->name('sources.qa.destroy');
        Route::post('sources/qa/{id}/unlink', AgentKnowledgeQaUnlinkController::class)->name('sources.qa.unlink');

        Route::get('sources/files', AgentSourcesFilesController::class)->name('sources.files');
        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->name('sources.files.upload');
        Route::delete('sources/text/{id}', AgentKnowledgeTextDestroyController::class)->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentSourceFileDeleteController::class)->name('sources.files.delete');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::post('sources/website', AgentSourceCrawlerController::class)->name('sources.website.crawl');
        Route::get('sources/websites/list', AgentSourceWebsitesListController::class)->name('sources.websites.list');
        Route::delete('sources/websites/{website}', AgentSourceWebsiteDeleteController::class)->name('sources.websites.delete');
        Route::get('sources/websites/{website}/links', AgentSourceCrawlerWebsiteLinksController::class)->name('sources.websites.links');
        Route::delete('sources/websites/{website}/links/{link}', AgentSourceCrawlerWebsiteLinkDeleteController::class)->name('sources.websites.links.delete');
        Route::post('sources/websites/{website}/links/{link}/retrain', AgentSourceCrawlerLinkRetrainController::class)->name('sources.websites.links.retrain');
        Route::post('sources/websites/{website}/links/bulk-delete', AgentSourceCrawlerWebsiteLinksBulkDeleteController::class)->name('sources.websites.links.bulk-delete');
        Route::post('sources/website/{website}/train', AgentSourceCrawlerWebsiteTrainController::class)->name('sources.website.train');

        // Starters API endpoints (used by Widget and Embed components)
        Route::post('starters', StartersStoreController::class)->name('starters.store');
        Route::delete('starters/{id}', StarterDeleteController::class)->name('starters.delete');

        Route::get('leads', AgentLeadsController::class)->name('leads');
        Route::get('leads/data', AgentLeadsDataController::class)->name('leads.data');
        Route::delete('leads', AgentLeadsDeleteController::class)->name('leads.delete');
        Route::patch('leads/settings', AgentLeadsUpdateSettingsController::class)->name('leads.settings');
        Route::post('leads/export', AgentLeadsExportController::class)->name('leads.export');

        Route::get('logs', AgentChatLogController::class)->name('logs');
        Route::get('logs/conversations', AgentChatLogConversationsController::class)->name('logs.conversations');
        Route::get('logs/messages/{conversation}', AgentChatLogMessagesController::class)->name('logs.messages');
        Route::get('logs/conversations/{conversation}/pulse', AgentChatLogConversationPulseController::class)->name('logs.pulse');
        Route::get('logs/topics', AgentChatLogTopicsController::class)->name('logs.topics');
        Route::get('logs/countries', AgentChatLogCountriesController::class)->name('logs.countries');
        Route::post('logs/revise-answer', AgentChatLogReviseAnswerController::class)->name('logs.revise-answer');

        // Handoff (Admin Takeover) Routes
        Route::get('handoff/pending', HandoffPendingListController::class)->name('handoff.pending');
        Route::post('handoff/{conversation}/takeover', HandoffTakeOverController::class)->name('handoff.takeover');
        Route::post('handoff/{conversation}/return', HandoffReturnController::class)->name('handoff.return');
        Route::post('handoff/{conversation}/message', HandoffMessageController::class)->name('handoff.message');
        Route::post('handoff/{conversation}/typing', HandoffTypingController::class)->name('handoff.typing');
        Route::post('handoff/{conversation}/mark-read', HandoffMarkReadController::class)->name('handoff.mark-read');

        Route::get('widget', WidgetController::class)->name('widget');
        Route::get('embed', EmbedController::class)->name('embed');

        //        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');
        Route::patch('security', AgentSecurityUpdateController::class)->name('security.update');

        // Settings
        Route::post('settings/{group}', AgentUpdateSettingsController::class)->name('settings.update');
        Route::delete('settings/{group}', AgentDeleteSettingsController::class)->name('settings.delete');

        // Models API
        Route::get('/models/list', AgentModelsListController::class)->name('models.list');
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

        // Rename Agent
        Route::patch('rename', AgentRenameController::class)->name('rename');

        // Update Agent
        Route::patch('update', AgentUpdateController::class)->name('update');

        // Linking / Unlinking Source Files
        Route::get('sources/trained-files', AgentSourcesTrainedListController::class)->name('sources.list');
        Route::post('sources/trained-files/{fileId}/link', AgentSourcesLinkFileController::class)->name('sources.files.link');
        Route::post('sources/trained-files/{fileId}/unlink', AgentSourceUnlinkFileController::class)->name('sources.files.unlink');

        // Text Sources
        Route::get('sources/trained-texts', AgentSourcesTrainedTextsListController::class)->name('sources.texts.list');
        Route::post('sources/trained-texts/{textId}/link', AgentSourcesLinkTextController::class)->name('sources.texts.link');
        Route::post('sources/trained-texts/{textId}/unlink', AgentSourceUnlinkTextController::class)->name('sources.texts.unlink');

        // Website Sources
        Route::get('sources/trained-websites', AgentSourcesTrainedWebsitesListController::class)->name('sources.trained-websites.list');
        Route::post('sources/trained-websites/{websiteId}/link', AgentSourcesLinkWebsiteController::class)->name('sources.trained-websites.link');
        Route::post('sources/websites/{websiteId}/unlink', AgentSourceWebsiteUnlinkController::class)->name('sources.websites.unlink');

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
