<?php

use App\Http\Controllers\Agents\AgentManagerController;
use App\Http\Controllers\Agents\AgentsController;
use App\Http\Controllers\Agents\Analytics\Metrics\AgentMetricsController;
use App\Http\Controllers\Agents\Analytics\Metrics\AgentMetricsDataController;
use App\Http\Controllers\Agents\Analytics\Overview\AgentAnalyticsController;
use App\Http\Controllers\Agents\Analytics\Overview\AgentAnalyticsDataController;
use App\Http\Controllers\Agents\Analytics\Overview\AgentChartDataController;
use App\Http\Controllers\Agents\Analytics\Sentiment\AgentSentimentController;
use App\Http\Controllers\Agents\Analytics\Sentiment\AgentSentimentDataController;
use App\Http\Controllers\Agents\Analytics\Sources\AgentSourcesController;
use App\Http\Controllers\Agents\Analytics\Sources\AgentSourcesDataController;
use App\Http\Controllers\Agents\Analytics\Tone\AgentToneController;
use App\Http\Controllers\Agents\Analytics\Tone\AgentToneDataController;
use App\Http\Controllers\Agents\Analytics\Topics\AgentTopicsController;
use App\Http\Controllers\Agents\Analytics\Topics\AgentTopicsDataController;
use App\Http\Controllers\Agents\Bar\BarPreviewController;
use App\Http\Controllers\Agents\BusinessProfile\AgentBusinessProfileController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogController;
use App\Http\Controllers\Agents\ChatLog\AgentChatLogReviseAnswerController;
use App\Http\Controllers\Agents\ChatLog\ConversationMessagesController as ChatLogConversationMessagesController;
use App\Http\Controllers\Agents\ChatLog\ConversationPulseController as ChatLogConversationPulseController;
use App\Http\Controllers\Agents\ChatLog\ConversationsController as ChatLogConversationsController;
use App\Http\Controllers\Agents\ChatLog\CountriesController as ChatLogCountriesController;
use App\Http\Controllers\Agents\ChatLog\TopicsController as ChatLogTopicsController;
use App\Http\Controllers\Agents\Handoff\AgentHandoffController;
use App\Http\Controllers\Agents\Handoff\HandoffTypingController;
use App\Http\Controllers\Agents\Handoff\MarkHandoffReadController;
use App\Http\Controllers\Agents\Handoff\ReturnHandoffController;
use App\Http\Controllers\Agents\Handoff\SendHandoffMessageController;
use App\Http\Controllers\Agents\Handoff\TakeoverHandoffController;
use App\Http\Controllers\Agents\Identity\AgentIdentityController;
use App\Http\Controllers\Agents\Identity\RegenerateAgentAuthSecretController;
use App\Http\Controllers\Agents\Leads\AgentLeadsController;
use App\Http\Controllers\Agents\Leads\AgentLeadsExportController;
use App\Http\Controllers\Agents\Leads\AgentLeadsSettingsController;
use App\Http\Controllers\Agents\Members\AgentMembersController;
use App\Http\Controllers\Agents\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agents\Playground\AgentPlaygroundController;
use App\Http\Controllers\Agents\RenameAgentController;
use App\Http\Controllers\Agents\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agents\Settings\AgentSettingsController;
use App\Http\Controllers\Agents\Setup\SetupController;
use App\Http\Controllers\Agents\Setup\UpdateAgentLlmController;
use App\Http\Controllers\Agents\Sources\Audio\AgentSourceAudioController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorAttachController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorDisconnectController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorRefreshController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorsController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorsListController;
use App\Http\Controllers\Agents\Sources\Connectors\AgentConnectorToolsController;
use App\Http\Controllers\Agents\Sources\Files\Manage\AgentFileSummaryController;
use App\Http\Controllers\Agents\Sources\Files\Manage\AgentKnowledgeSourcesStatsController;
use App\Http\Controllers\Agents\Sources\Files\Manage\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agents\Sources\Files\Manage\AgentSourcesFilesController;
use App\Http\Controllers\Agents\Sources\Files\Manage\AgentTrainedFilesController;
use App\Http\Controllers\Agents\Sources\Files\Pages\AgentKnowledgeFilesController;
use App\Http\Controllers\Agents\Sources\Files\Pages\AgentKnowledgeMarkdownController;
use App\Http\Controllers\Agents\Sources\Files\Pages\AgentKnowledgeSourcesController;
use App\Http\Controllers\Agents\Sources\Files\Pages\AgentKnowledgeWebController;
use App\Http\Controllers\Agents\Sources\Files\Upload\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agents\Sources\Files\Upload\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agents\Sources\Files\Upload\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agents\Sources\Functions\AgentFunctionAttachController;
use App\Http\Controllers\Agents\Sources\Functions\AgentFunctionDeleteController;
use App\Http\Controllers\Agents\Sources\Functions\AgentFunctionsController;
use App\Http\Controllers\Agents\Sources\Functions\AgentFunctionsListController;
use App\Http\Controllers\Agents\Sources\Functions\AgentFunctionTestController;
use App\Http\Controllers\Agents\Sources\Qa\AgentQaController;
use App\Http\Controllers\Agents\Sources\Qa\DetachAgentQaController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesLinkTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourcesTrainedTextsListController;
use App\Http\Controllers\Agents\Sources\Text\AgentSourceUnlinkTextController;
use App\Http\Controllers\Agents\Sources\Text\AgentTextController;
use App\Http\Controllers\Agents\Sources\Text\DetachAgentTextController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceFileDeleteController;
use App\Http\Controllers\Agents\Sources\Video\AgentSourceVideoController;
use App\Http\Controllers\Agents\Sources\Web\AgentTrainedWebsitesController;
use App\Http\Controllers\Agents\Sources\Web\AgentWebsiteLinksController;
use App\Http\Controllers\Agents\Sources\Web\AgentWebsitesController;
use App\Http\Controllers\Agents\Sources\Web\BulkDestroyAgentWebsiteLinksController;
use App\Http\Controllers\Agents\Sources\Web\RetrainAgentWebsiteLinkController;
use App\Http\Controllers\Agents\Sources\Web\TrainAgentWebsiteController;
use App\Http\Controllers\Agents\Sources\Youtube\AgentYoutubeController;
use App\Http\Controllers\Agents\Sources\Youtube\RetrainAgentYoutubeController;
use App\Http\Controllers\Agents\Starters\AgentStartersController;
use App\Http\Controllers\Agents\SubDomain\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agents\Team\AgentTeamController;
use App\Http\Controllers\Agents\Topics\AgentTopicsController as TopicsResourceController;
use App\Http\Controllers\Agents\Widget\WidgetPreviewController;
use App\Http\Controllers\Agents\Workflow\AgentWorkflowController;
use App\Http\Controllers\Countries\CountriesListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'user.subscribed', 'can.access.dashboard'])->prefix('my/')->group(function (): void {
    Route::get('agents', [AgentsController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentsController::class, 'store'])->name('agents.store');

    Route::get('countries/list', CountriesListController::class)->name('countries.list');

    Route::prefix('agents/{agent}')->name('agents.')->middleware('can.access.agent')->group(function (): void {
        Route::get('home', AgentManagerController::class)->name('manager');
        Route::get('analytics', AgentAnalyticsController::class)->name('analytics');
        Route::get('analytics/data', AgentAnalyticsDataController::class)->name('analytics.data');
        Route::get('analytics/chart-data', AgentChartDataController::class)->name('analytics.chart-data');
        Route::get('analytics/metrics', AgentMetricsController::class)->name('analytics.metrics');
        Route::get('analytics/metrics/data', AgentMetricsDataController::class)->name('metrics.data');
        Route::get('analytics/sentiment', AgentSentimentController::class)->name('analytics.sentiment');
        Route::get('analytics/sentiment/data', AgentSentimentDataController::class)->middleware('capability:sentiment')->name('sentiment.data');
        Route::get('analytics/tone', AgentToneController::class)->name('analytics.tone');
        Route::get('analytics/tone/data', AgentToneDataController::class)->middleware('capability:tone')->name('tone.data');
        Route::get('analytics/topics', AgentTopicsController::class)->name('analytics.topics');
        Route::get('analytics/topics/data', AgentTopicsDataController::class)->middleware('capability:topics')->name('topics.data');
        Route::get('analytics/sources', AgentSourcesController::class)->name('analytics.sources');
        Route::get('analytics/sources/data', AgentSourcesDataController::class)->name('analytics.sources.data');

        // Topics Management — the index page is allowed through so the Vue
        // LockedFeature card can render on Starter/Standard; the mutations
        // are hard-gated.
        Route::get('topics', [TopicsResourceController::class, 'index'])->name('topics.index');
        Route::middleware('capability:topics')->group(function (): void {
            Route::post('topics', [TopicsResourceController::class, 'store'])->name('topics.store');
            Route::patch('topics/{topic}', [TopicsResourceController::class, 'update'])->name('topics.update');
            Route::delete('topics/{topic}', [TopicsResourceController::class, 'destroy'])->name('topics.destroy');
        });

        // Update Agent (Persona/Instructions)
        Route::patch('/update', [AgentsController::class, 'update'])->name('update');

        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('bar/preview', BarPreviewController::class)->name('bar.preview');

        // Playground — a single page with an in-page surface toggle (Hosted Chat
        // / Widget / Bar). Widget and Bar previews render the agent embedded in
        // a real-looking host page so the admin sees the real customer-facing
        // experience, not a configurator chrome.
        Route::get('playground', AgentPlaygroundController::class)->name('playground');

        Route::get('persona', AgentShowPersonaController::class)->name('persona');

        Route::get('business-profile', [AgentBusinessProfileController::class, 'show'])->name('business-profile');
        Route::patch('business-profile', [AgentBusinessProfileController::class, 'update'])->name('business-profile.update');
        Route::delete('business-profile', [AgentBusinessProfileController::class, 'destroy'])->name('business-profile.destroy');

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
        Route::post('sources/youtube/{youtube}/retrain', RetrainAgentYoutubeController::class)->name('sources.youtube.retrain');

        Route::get('sources/text', [AgentTextController::class, 'index'])->name('sources.text.index');
        Route::post('sources/text', [AgentTextController::class, 'store'])->name('sources.text.store');
        Route::get('sources/text/{id}', [AgentTextController::class, 'show'])->name('sources.text.show');
        Route::patch('sources/text/{id}', [AgentTextController::class, 'update'])->name('sources.text.update');
        Route::post('sources/text/{id}/unlink', DetachAgentTextController::class)->name('sources.text.unlink');

        Route::get('sources/qa', [AgentQaController::class, 'index'])->name('sources.qa.index');
        Route::post('sources/qa', [AgentQaController::class, 'store'])->name('sources.qa.store');
        Route::get('sources/qa/{id}', [AgentQaController::class, 'show'])->name('sources.qa.show');
        Route::patch('sources/qa/{id}', [AgentQaController::class, 'update'])->name('sources.qa.update');
        Route::delete('sources/qa/{id}', [AgentQaController::class, 'destroy'])->name('sources.qa.destroy');
        Route::post('sources/qa/{id}/unlink', DetachAgentQaController::class)->name('sources.qa.unlink');

        // Functions (LLM tool calls — webhook-backed). Functions are owned at
        // the account level (Settings → Functions); on the agent screen the
        // user only attaches / detaches them. Attach = pivot exists; detach
        // = pivot deleted. No per-attachment enabled flag.
        Route::get('sources/functions', AgentFunctionsController::class)->name('sources.functions.index');
        Route::get('sources/functions/data', AgentFunctionsListController::class)->name('sources.functions.data');
        Route::post('sources/functions/{function}/attach', AgentFunctionAttachController::class)->name('sources.functions.attach');
        Route::delete('sources/functions/{function}', AgentFunctionDeleteController::class)->name('sources.functions.detach');
        Route::post('sources/functions/{function}/test', AgentFunctionTestController::class)->name('sources.functions.test');

        // Connectors page — agents attach existing account-level connectors
        // via the pivot. Creation/deletion of connector rows happens in
        // /settings/connectors. Attach = pivot exists; detach = pivot deleted.
        Route::get('sources/connectors', AgentConnectorsController::class)->name('sources.connectors.index');
        Route::get('sources/connectors/data', AgentConnectorsListController::class)->name('sources.connectors.data');
        Route::post('sources/connectors/{slug}/attach', AgentConnectorAttachController::class)->name('sources.connectors.attach');
        Route::delete('sources/connectors/{slug}', AgentConnectorDisconnectController::class)->name('sources.connectors.detach');
        Route::get('sources/connectors/{slug}/tools', AgentConnectorToolsController::class)->name('sources.connectors.tools');
        Route::post('sources/connectors/{slug}/refresh', AgentConnectorRefreshController::class)->name('sources.connectors.refresh');

        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->name('sources.files.upload');
        Route::delete('sources/text/{id}', [AgentTextController::class, 'destroy'])->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentSourceFileDeleteController::class)->name('sources.files.delete');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::get('sources/websites', [AgentWebsitesController::class, 'index'])->name('sources.websites.index');
        Route::post('sources/websites', [AgentWebsitesController::class, 'store'])->name('sources.websites.store');
        Route::delete('sources/websites/{website}', [AgentWebsitesController::class, 'destroy'])->name('sources.websites.destroy');
        Route::post('sources/websites/{website}/train', TrainAgentWebsiteController::class)->name('sources.websites.train');

        Route::get('sources/websites/{website}/links', [AgentWebsiteLinksController::class, 'index'])->name('sources.websites.links.index');
        Route::delete('sources/websites/{website}/links/{link}', [AgentWebsiteLinksController::class, 'destroy'])->name('sources.websites.links.destroy');
        Route::post('sources/websites/{website}/links/{link}/retrain', RetrainAgentWebsiteLinkController::class)->name('sources.websites.links.retrain');
        Route::post('sources/websites/{website}/links/bulk-destroy', BulkDestroyAgentWebsiteLinksController::class)->name('sources.websites.links.bulk-destroy');

        // Starters API endpoint (used by Widget and Embed components)
        Route::put('starters', [AgentStartersController::class, 'update'])->name('starters.update');

        Route::get('leads', [AgentLeadsController::class, 'index'])->name('leads.index');
        Route::delete('leads', [AgentLeadsController::class, 'destroy'])->name('leads.destroy');
        Route::patch('leads/settings', AgentLeadsSettingsController::class)->name('leads.settings');
        Route::post('leads/export', AgentLeadsExportController::class)->name('leads.export');

        // Chatter members. index lists every workspace chatter (with a
        // `has_access` flag for this agent); store invites a brand-new
        // chatter; grant gives an existing chatter access to this agent;
        // destroy revokes this agent's grant (user record stays).
        Route::get('members', [AgentMembersController::class, 'index'])->name('members.index');
        Route::post('members', [AgentMembersController::class, 'store'])->name('members.store');
        Route::post('members/{chatter}/grant', [AgentMembersController::class, 'grant'])->name('members.grant');
        Route::delete('members/{chatter}', [AgentMembersController::class, 'destroy'])->name('members.destroy');

        Route::get('logs', [AgentChatLogController::class, 'index'])->name('logs');
        Route::get('logs/conversations', [ChatLogConversationsController::class, 'index'])->name('logs.conversations');
        Route::get('logs/messages/{conversation}', ChatLogConversationMessagesController::class)->name('logs.messages');
        Route::get('logs/conversations/{conversation}/pulse', ChatLogConversationPulseController::class)->middleware('capability:pulse')->name('logs.pulse');
        Route::get('logs/topics', ChatLogTopicsController::class)->name('logs.topics');
        Route::get('logs/countries', ChatLogCountriesController::class)->name('logs.countries');
        Route::delete('logs/conversations/{conversation}', [ChatLogConversationsController::class, 'destroy'])->name('logs.delete-conversation');
        Route::post('logs/revise-answer', AgentChatLogReviseAnswerController::class)->name('logs.revise-answer');

        // Handoff (Admin Takeover) Routes — Pro-only capability.
        Route::middleware('capability:handoff')->group(function (): void {
            Route::get('handoff/pending', [AgentHandoffController::class, 'index'])->name('handoff.pending');
            Route::post('handoff/{conversation}/takeover', TakeoverHandoffController::class)->name('handoff.takeover');
            Route::post('handoff/{conversation}/return', ReturnHandoffController::class)->name('handoff.return');
            Route::post('handoff/{conversation}/message', SendHandoffMessageController::class)->name('handoff.message');
            Route::post('handoff/{conversation}/typing', HandoffTypingController::class)->name('handoff.typing');
            Route::post('handoff/{conversation}/mark-read', MarkHandoffReadController::class)->name('handoff.mark-read');
        });

        // Setup — one unified configuration page for every agent. All content
        // settings (text / persona / starters / model / branding) are shared
        // across the three surfaces; the top tab on the page is a preview
        // switcher, not a separate config. Surface-specific Behavior + Embed
        // fields swap on the active preview tab and write to their own
        // settings groups (widget / bar / embed).
        Route::get('setup', SetupController::class)->name('setup');

        // Single-model picker — sets the agent's `llm_id` and hyperparameters.
        Route::patch('llm', UpdateAgentLlmController::class)->name('llm.update');

        //        Route::patch('activity', AgentUpdateActivityController::class)->name('activity');
        Route::patch('security', AgentSecurityUpdateController::class)->name('security.update');

        // Per-agent signing secret. Owner mints sign-in tokens (chat ?token= and
        // widget data-auth) on their backend; regenerating invalidates every
        // token previously signed for this agent.
        Route::get('identity', AgentIdentityController::class)->name('identity');
        Route::post('identity/regenerate', RegenerateAgentAuthSecretController::class)->name('identity.regenerate');

        // Settings
        Route::post('settings/{group}', [AgentSettingsController::class, 'update'])->name('settings.update');
        Route::delete('settings/{group}', [AgentSettingsController::class, 'destroy'])->name('settings.delete');

        // Members
        Route::get('team', AgentTeamController::class)->name('team');

        // Workflow
        Route::get('workflow', AgentWorkflowController::class)->name('workflow');

        // Rename Agent
        Route::patch('rename', RenameAgentController::class)->name('rename');

        // Linking / Unlinking Source Files
        Route::get('sources/trained-files', [AgentTrainedFilesController::class, 'index'])->name('sources.list');
        Route::post('sources/trained-files/{fileId}/link', [AgentTrainedFilesController::class, 'store'])->name('sources.files.link');
        Route::post('sources/trained-files/{file}/unlink', [AgentTrainedFilesController::class, 'destroy'])->name('sources.files.unlink');

        // Text Sources
        Route::get('sources/trained-texts', AgentSourcesTrainedTextsListController::class)->name('sources.texts.list');
        Route::post('sources/trained-texts/{textId}/link', AgentSourcesLinkTextController::class)->name('sources.texts.link');
        Route::post('sources/trained-texts/{textId}/unlink', AgentSourceUnlinkTextController::class)->name('sources.texts.unlink');

        // Website Sources (cross-agent shared pool)
        Route::get('sources/trained-websites', [AgentTrainedWebsitesController::class, 'index'])->name('sources.trained-websites.index');
        Route::post('sources/trained-websites/{websiteId}/link', [AgentTrainedWebsitesController::class, 'store'])->name('sources.trained-websites.link');
        Route::post('sources/trained-websites/{websiteId}/unlink', [AgentTrainedWebsitesController::class, 'destroy'])->name('sources.trained-websites.unlink');

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
