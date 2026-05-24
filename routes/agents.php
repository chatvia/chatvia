<?php

use App\Http\Controllers\Agents\AgentChatLogController;
use App\Http\Controllers\Agents\AgentChatLogReviseAnswerController;
use App\Http\Controllers\Agents\AgentFileSummaryController;
use App\Http\Controllers\Agents\AgentHandoffController;
use App\Http\Controllers\Agents\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\AgentLeadsController;
use App\Http\Controllers\Agents\AgentLeadsExportController;
use App\Http\Controllers\Agents\AgentLeadsSettingsController;
use App\Http\Controllers\Agents\AgentManagerController;
use App\Http\Controllers\Agents\AgentModelsController;
use App\Http\Controllers\Agents\AgentModelsSettingsController;
use App\Http\Controllers\Agents\AgentPlaygroundController;
use App\Http\Controllers\Agents\AgentsController;
use App\Http\Controllers\Agents\AgentSettingsController;
use App\Http\Controllers\Agents\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agents\AgentStartersController;
use App\Http\Controllers\Agents\AgentTopicsController as TopicsResourceController;
use App\Http\Controllers\Agents\AgentTrainedFilesController;
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
use App\Http\Controllers\Agents\Embed\EmbedController;
use App\Http\Controllers\Agents\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agents\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeMarkdownController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeSourcesStatsController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Sources\AgentKnowledgeWebController;
use App\Http\Controllers\Agents\Sources\AgentQaController;
use App\Http\Controllers\Agents\Sources\AgentSourcesFilesController;
use App\Http\Controllers\Agents\Sources\AgentTextController;
use App\Http\Controllers\Agents\Sources\AgentYoutubeController;
use App\Http\Controllers\Agents\Sources\Audio\AgentSourceAudioController;
use App\Http\Controllers\Agents\Sources\AgentTrainedWebsitesController;
use App\Http\Controllers\Agents\Sources\AgentWebsiteLinksController;
use App\Http\Controllers\Agents\Sources\AgentWebsitesController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesLinkTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesTrainedTextsListController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourceUnlinkTextController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceFileDeleteController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceVideoController;
use App\Http\Controllers\Agents\Team\AgentTeamController;
use App\Http\Controllers\Agents\Widget\WidgetController;
use App\Http\Controllers\Agents\Widget\WidgetPreviewController;
use App\Http\Controllers\Agents\Workflow\AgentWorkflowController;
use App\Http\Controllers\CountriesListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'user.subscribed'])->prefix('my/')->group(function () {
    Route::get('agents', [AgentsController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentsController::class, 'store'])->name('agents.store');

    Route::get('countries/list', CountriesListController::class)->name('countries.list');

    Route::prefix('agents/{agent}')->name('agents.')->middleware('can.access.agent')->group(function () {
        Route::get('home', AgentManagerController::class)->name('manager');
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
        Route::get('topics', [TopicsResourceController::class, 'index'])->name('topics.index');
        Route::post('topics', [TopicsResourceController::class, 'store'])->name('topics.store');
        Route::patch('topics/{topic}', [TopicsResourceController::class, 'update'])->name('topics.update');
        Route::delete('topics/{topic}', [TopicsResourceController::class, 'destroy'])->name('topics.destroy');

        // Update Agent (Persona/Instructions)
        Route::patch('/update', [AgentsController::class, 'update'])->name('update');

        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('playground', AgentPlaygroundController::class)->name('playground');

        Route::get('persona', AgentShowPersonaController::class)->name('persona');

        // Knowledge
        Route::get('sources', AgentKnowledgeSourcesController::class)->name('sources');
        Route::get('sources/stats', AgentKnowledgeSourcesStatsController::class)->name('sources.stats');
        Route::get('sources/files', AgentKnowledgeFilesController::class)->name('sources.files');
        Route::get('sources/files/data', AgentSourcesFilesController::class)->name('sources.files.data');
        Route::get('sources/markdown', AgentKnowledgeMarkdownController::class)->name('sources.markdown');
        Route::get('sources/audio', AgentSourceAudioController::class)->name('sources.audio');
        Route::get('sources/video', AgentSourceVideoController::class)->name('sources.video');
        Route::get('sources/web', AgentKnowledgeWebController::class)->name('sources.web');
        Route::get('sources/youtube', [AgentYoutubeController::class, 'index'])->name('sources.youtube.index');
        Route::post('sources/youtube', [AgentYoutubeController::class, 'store'])->name('sources.youtube.store');
        Route::delete('sources/youtube/{youtube}', [AgentYoutubeController::class, 'destroy'])->name('sources.youtube.destroy');
        Route::post('sources/youtube/{youtube}/retrain', [AgentYoutubeController::class, 'retrain'])->name('sources.youtube.retrain');

        Route::get('sources/text', [AgentTextController::class, 'index'])->name('sources.text.index');
        Route::post('sources/text', [AgentTextController::class, 'store'])->name('sources.text.store');
        Route::get('sources/text/{id}', [AgentTextController::class, 'show'])->name('sources.text.show');
        Route::patch('sources/text/{id}', [AgentTextController::class, 'update'])->name('sources.text.update');
        Route::post('sources/text/{id}/unlink', [AgentTextController::class, 'unlink'])->name('sources.text.unlink');

        Route::get('sources/qa', [AgentQaController::class, 'index'])->name('sources.qa.index');
        Route::post('sources/qa', [AgentQaController::class, 'store'])->name('sources.qa.store');
        Route::get('sources/qa/{id}', [AgentQaController::class, 'show'])->name('sources.qa.show');
        Route::patch('sources/qa/{id}', [AgentQaController::class, 'update'])->name('sources.qa.update');
        Route::delete('sources/qa/{id}', [AgentQaController::class, 'destroy'])->name('sources.qa.destroy');
        Route::post('sources/qa/{id}/unlink', [AgentQaController::class, 'unlink'])->name('sources.qa.unlink');

        // Functions (LLM tool calls — webhook-backed)
        Route::get('sources/functions', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionsController::class)->name('sources.functions.index');
        Route::get('sources/functions/data', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionsListController::class)->name('sources.functions.data');
        Route::post('sources/functions', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionStoreController::class)->name('sources.functions.store');
        Route::patch('sources/functions/{function}', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionUpdateController::class)->name('sources.functions.update');
        Route::post('sources/functions/{function}/toggle', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionToggleController::class)->name('sources.functions.toggle');
        Route::post('sources/functions/{function}/test', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionTestController::class)->name('sources.functions.test');
        Route::delete('sources/functions/{function}', \App\Http\Controllers\Agents\Sources\Functions\AgentFunctionDeleteController::class)->name('sources.functions.delete');

        // Connectors (preset-backed MCP servers — Notion, Stripe, GitHub, …)
        Route::get('sources/connectors', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorsController::class)->name('sources.connectors.index');
        Route::get('sources/connectors/data', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorsListController::class)->name('sources.connectors.data');
        Route::post('sources/connectors/custom', \App\Http\Controllers\Agents\Sources\Connectors\AgentCustomConnectorAddController::class)->name('sources.connectors.custom.add');
        Route::post('sources/connectors/{slug}/connect', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorConnectController::class)->name('sources.connectors.connect');
        Route::get('sources/connectors/{slug}/oauth/start', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorOAuthStartController::class)->name('sources.connectors.oauth.start');
        Route::post('sources/connectors/{slug}/refresh', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorRefreshController::class)->name('sources.connectors.refresh');
        Route::get('sources/connectors/{slug}/tools', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorToolsController::class)->name('sources.connectors.tools');
        Route::post('sources/connectors/{slug}/enable', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorEnableController::class)->name('sources.connectors.enable');
        Route::delete('sources/connectors/{slug}', \App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorDisconnectController::class)->name('sources.connectors.disconnect');

        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->name('sources.files.upload');
        Route::delete('sources/text/{id}', [AgentTextController::class, 'destroy'])->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentSourceFileDeleteController::class)->name('sources.files.delete');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::get('sources/websites', [AgentWebsitesController::class, 'index'])->name('sources.websites.index');
        Route::post('sources/websites', [AgentWebsitesController::class, 'store'])->name('sources.websites.store');
        Route::delete('sources/websites/{website}', [AgentWebsitesController::class, 'destroy'])->name('sources.websites.destroy');
        Route::post('sources/websites/{website}/train', [AgentWebsitesController::class, 'train'])->name('sources.websites.train');

        Route::get('sources/websites/{website}/links', [AgentWebsiteLinksController::class, 'index'])->name('sources.websites.links.index');
        Route::delete('sources/websites/{website}/links/{link}', [AgentWebsiteLinksController::class, 'destroy'])->name('sources.websites.links.destroy');
        Route::post('sources/websites/{website}/links/{link}/retrain', [AgentWebsiteLinksController::class, 'retrain'])->name('sources.websites.links.retrain');
        Route::post('sources/websites/{website}/links/bulk-destroy', [AgentWebsiteLinksController::class, 'bulkDestroy'])->name('sources.websites.links.bulk-destroy');

        // Starters API endpoints (used by Widget and Embed components)
        Route::post('starters', [AgentStartersController::class, 'store'])->name('starters.store');
        Route::delete('starters/{id}', [AgentStartersController::class, 'destroy'])->name('starters.destroy');

        Route::get('leads', [AgentLeadsController::class, 'index'])->name('leads.index');
        Route::delete('leads', [AgentLeadsController::class, 'destroy'])->name('leads.destroy');
        Route::patch('leads/settings', AgentLeadsSettingsController::class)->name('leads.settings');
        Route::post('leads/export', AgentLeadsExportController::class)->name('leads.export');

        Route::get('logs', [AgentChatLogController::class, 'index'])->name('logs');
        Route::get('logs/conversations', [AgentChatLogController::class, 'conversations'])->name('logs.conversations');
        Route::get('logs/messages/{conversation}', [AgentChatLogController::class, 'messages'])->name('logs.messages');
        Route::get('logs/conversations/{conversation}/pulse', [AgentChatLogController::class, 'pulse'])->name('logs.pulse');
        Route::get('logs/topics', [AgentChatLogController::class, 'topics'])->name('logs.topics');
        Route::get('logs/countries', [AgentChatLogController::class, 'countries'])->name('logs.countries');
        Route::delete('logs/conversations/{conversation}', [AgentChatLogController::class, 'destroyConversation'])->name('logs.delete-conversation');
        Route::post('logs/revise-answer', AgentChatLogReviseAnswerController::class)->name('logs.revise-answer');

        // Handoff (Admin Takeover) Routes
        Route::get('handoff/pending', [AgentHandoffController::class, 'index'])->name('handoff.pending');
        Route::post('handoff/{conversation}/takeover', [AgentHandoffController::class, 'takeover'])->name('handoff.takeover');
        Route::post('handoff/{conversation}/return', [AgentHandoffController::class, 'return'])->name('handoff.return');
        Route::post('handoff/{conversation}/message', [AgentHandoffController::class, 'message'])->name('handoff.message');
        Route::post('handoff/{conversation}/typing', [AgentHandoffController::class, 'typing'])->name('handoff.typing');
        Route::post('handoff/{conversation}/mark-read', [AgentHandoffController::class, 'markRead'])->name('handoff.mark-read');

        Route::get('widget', WidgetController::class)->name('widget');
        Route::get('embed', EmbedController::class)->name('embed');

        //        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');
        Route::patch('security', AgentSecurityUpdateController::class)->name('security.update');

        // Settings
        Route::post('settings/{group}', [AgentSettingsController::class, 'update'])->name('settings.update');
        Route::delete('settings/{group}', [AgentSettingsController::class, 'destroy'])->name('settings.delete');

        // Models API
        Route::get('/models', [AgentModelsController::class, 'index'])->name('models.index');
        Route::patch('/models/toggle', [AgentModelsController::class, 'toggle'])->name('models.toggle');
        Route::patch('/models/settings', AgentModelsSettingsController::class)->name('models.settings.update');

        // Members
        Route::get('team', AgentTeamController::class)->name('team');

        // Workflow
        Route::get('workflow', AgentWorkflowController::class)->name('workflow');

        // Rename Agent
        Route::patch('rename', [AgentsController::class, 'rename'])->name('rename');

        // Linking / Unlinking Source Files
        Route::get('sources/trained-files', [AgentTrainedFilesController::class, 'index'])->name('sources.list');
        Route::post('sources/trained-files/{fileId}/link', [AgentTrainedFilesController::class, 'link'])->name('sources.files.link');
        Route::post('sources/trained-files/{file}/unlink', [AgentTrainedFilesController::class, 'unlink'])->name('sources.files.unlink');

        // Text Sources
        Route::get('sources/trained-texts', AgentSourcesTrainedTextsListController::class)->name('sources.texts.list');
        Route::post('sources/trained-texts/{textId}/link', AgentSourcesLinkTextController::class)->name('sources.texts.link');
        Route::post('sources/trained-texts/{textId}/unlink', AgentSourceUnlinkTextController::class)->name('sources.texts.unlink');

        // Website Sources (cross-agent shared pool)
        Route::get('sources/trained-websites', [AgentTrainedWebsitesController::class, 'index'])->name('sources.trained-websites.index');
        Route::post('sources/trained-websites/{websiteId}/link', [AgentTrainedWebsitesController::class, 'link'])->name('sources.trained-websites.link');
        Route::post('sources/trained-websites/{websiteId}/unlink', [AgentTrainedWebsitesController::class, 'unlink'])->name('sources.trained-websites.unlink');

        // Delete Agent
        Route::delete('/', [AgentsController::class, 'destroy'])->name('delete');

    });

    Route::post('agents/knowledge/files/analyze', AgentKnowledgeFilesAnalyzeController::class)->name('agents.knowledge.files.analyze');

    Route::get('generate-random-subdomain', AgentsGenerateRandomSubDomainController::class)->name('generate-random-subdomain');
});

// Removed - old member invitation system
// Route::get('agents/{agent}/invitations/{token}', AgentMembersInvitationCreateAccountController::class)
//     ->name('agents.invitations');
//
// Route::post('agents/{agent}/invitations/{token}/accept', AgentMembersInvitationAcceptController::class)
//     ->name('agents.invitations.accept');
