<?php

use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Agent\AgentManagerController;
use App\Http\Controllers\Agent\Analytics\Overview\AgentAnalyticsController;
use App\Http\Controllers\Agent\Analytics\Overview\AgentAnalyticsDataController;
use App\Http\Controllers\Agent\Analytics\Overview\AgentChartDataController;
use App\Http\Controllers\Agent\Analytics\Sentiment\AgentSentimentController;
use App\Http\Controllers\Agent\Analytics\Sentiment\AgentSentimentDataController;
use App\Http\Controllers\Agent\Analytics\Source\AgentSourceController;
use App\Http\Controllers\Agent\Analytics\Source\AgentSourcesDataController;
use App\Http\Controllers\Agent\Analytics\Tone\AgentToneController;
use App\Http\Controllers\Agent\Analytics\Tone\AgentToneDataController;
use App\Http\Controllers\Agent\Analytics\Topic\AgentTopicController;
use App\Http\Controllers\Agent\Analytics\Topic\AgentTopicsDataController;
use App\Http\Controllers\Agent\Analytics\Topic\DismissSuggestedTopicController;
use App\Http\Controllers\Agent\Analytics\Topic\PromoteSuggestedTopicController;
use App\Http\Controllers\Agent\Analytics\Topic\SuggestedTopicsDataController;
use App\Http\Controllers\Agent\Bar\BarPreviewController;
use App\Http\Controllers\Agent\ChatLog\AgentChatLogController;
use App\Http\Controllers\Agent\ChatLog\AgentChatLogReviseAnswerController;
use App\Http\Controllers\Agent\ChatLog\ConversationController as ChatLogConversationsController;
use App\Http\Controllers\Agent\ChatLog\ConversationMessageController as ChatLogConversationMessagesController;
use App\Http\Controllers\Agent\ChatLog\ConversationPulseController as ChatLogConversationPulseController;
use App\Http\Controllers\Agent\ChatLog\CountryController as ChatLogCountriesController;
use App\Http\Controllers\Agent\ChatLog\TopicController as ChatLogTopicsController;
use App\Http\Controllers\Agent\Handoff\AgentHandoffController;
use App\Http\Controllers\Agent\Handoff\HandoffTypingController;
use App\Http\Controllers\Agent\Handoff\MarkHandoffReadController;
use App\Http\Controllers\Agent\Handoff\ReturnHandoffController;
use App\Http\Controllers\Agent\Handoff\SendHandoffMessageController;
use App\Http\Controllers\Agent\Handoff\TakeoverHandoffController;
use App\Http\Controllers\Agent\Identity\AgentIdentityController;
use App\Http\Controllers\Agent\Identity\RegenerateAgentAuthSecretController;
use App\Http\Controllers\Agent\Lead\AgentLeadController;
use App\Http\Controllers\Agent\Lead\AgentLeadSettingsController;
use App\Http\Controllers\Agent\Lead\AgentLeadsExportController;
use App\Http\Controllers\Agent\Member\AgentMemberController;
use App\Http\Controllers\Agent\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agent\Playground\AgentPlaygroundController;
use App\Http\Controllers\Agent\RenameAgentController;
use App\Http\Controllers\Agent\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agent\Settings\AgentSettingsController;
use App\Http\Controllers\Agent\Setup\SetupController;
use App\Http\Controllers\Agent\Setup\UpdateAgentDomainController;
use App\Http\Controllers\Agent\Setup\UpdateAgentLlmController;
use App\Http\Controllers\Agent\Source\Audio\AgentSourceAudioController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorAttachController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorDisconnectController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorRefreshController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorsListController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorToolController;
use App\Http\Controllers\Agent\Source\File\Manage\AgentFileSummaryController;
use App\Http\Controllers\Agent\Source\File\Manage\AgentKnowledgeSourcesStatsController;
use App\Http\Controllers\Agent\Source\File\Manage\AgentKnowledgeUploadedFilesDeleteController;
use App\Http\Controllers\Agent\Source\File\Manage\AgentSourceFileController;
use App\Http\Controllers\Agent\Source\File\Manage\AgentTrainedFileController;
use App\Http\Controllers\Agent\Source\File\Pages\AgentKnowledgeFileController;
use App\Http\Controllers\Agent\Source\File\Pages\AgentKnowledgeMarkdownController;
use App\Http\Controllers\Agent\Source\File\Pages\AgentKnowledgeSourceController;
use App\Http\Controllers\Agent\Source\File\Pages\AgentKnowledgeWebController;
use App\Http\Controllers\Agent\Source\File\Upload\AgentKnowledgeFilesAnalyzeController;
use App\Http\Controllers\Agent\Source\File\Upload\AgentKnowledgeFilesProcessController;
use App\Http\Controllers\Agent\Source\File\Upload\AgentKnowledgeFileUploadController;
use App\Http\Controllers\Agent\Source\Function\AgentFunctionAttachController;
use App\Http\Controllers\Agent\Source\Function\AgentFunctionController;
use App\Http\Controllers\Agent\Source\Function\AgentFunctionDeleteController;
use App\Http\Controllers\Agent\Source\Function\AgentFunctionsListController;
use App\Http\Controllers\Agent\Source\Function\AgentFunctionTestController;
use App\Http\Controllers\Agent\Source\Qa\AgentQaController;
use App\Http\Controllers\Agent\Source\Qa\DetachAgentQaController;
use App\Http\Controllers\Agent\Source\Text\AgentSourcesLinkTextController;
use App\Http\Controllers\Agent\Source\Text\AgentSourcesTrainedTextsListController;
use App\Http\Controllers\Agent\Source\Text\AgentSourceUnlinkTextController;
use App\Http\Controllers\Agent\Source\Text\AgentTextController;
use App\Http\Controllers\Agent\Source\Text\DetachAgentTextController;
use App\Http\Controllers\Agent\Source\Video\AgentSourceFileDeleteController;
use App\Http\Controllers\Agent\Source\Video\AgentSourceVideoController;
use App\Http\Controllers\Agent\Source\Web\AgentTrainedWebsiteController;
use App\Http\Controllers\Agent\Source\Web\AgentWebsiteController;
use App\Http\Controllers\Agent\Source\Web\AgentWebsiteLinkController;
use App\Http\Controllers\Agent\Source\Web\BulkDestroyAgentWebsiteLinksController;
use App\Http\Controllers\Agent\Source\Web\RetrainAgentWebsiteLinkController;
use App\Http\Controllers\Agent\Source\Web\TrainAgentWebsiteController;
use App\Http\Controllers\Agent\Source\Youtube\AgentYoutubeController;
use App\Http\Controllers\Agent\Source\Youtube\RetrainAgentYoutubeController;
use App\Http\Controllers\Agent\Starters\AgentStarterController;
use App\Http\Controllers\Agent\SubDomain\AgentsGenerateRandomSubDomainController;
use App\Http\Controllers\Agent\Team\AgentTeamController;
use App\Http\Controllers\Agent\Topic\AgentTopicController as TopicsResourceController;
use App\Http\Controllers\Agent\Widget\WidgetPreviewController;
use App\Http\Controllers\Countries\CountriesListController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'user.subscribed', 'can.access.dashboard'])->prefix('my/')->group(function (): void {
    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentController::class, 'store'])->name('agents.store');

    Route::get('countries/list', CountriesListController::class)->name('countries.list');

    Route::prefix('agents/{agent}')->name('agents.')->middleware('can.access.agent')->group(function (): void {
        Route::get('home', AgentManagerController::class)->name('manager');
        Route::get('analytics', AgentAnalyticsController::class)->name('analytics');
        Route::get('analytics/data', AgentAnalyticsDataController::class)->name('analytics.data');
        Route::get('analytics/chart-data', AgentChartDataController::class)->name('analytics.chart-data');
        Route::get('analytics/sentiment', AgentSentimentController::class)->name('analytics.sentiment');
        Route::get('analytics/sentiment/data', AgentSentimentDataController::class)->middleware('capability:sentiment')->name('sentiment.data');
        Route::get('analytics/tone', AgentToneController::class)->name('analytics.tone');
        Route::get('analytics/tone/data', AgentToneDataController::class)->middleware('capability:tone')->name('tone.data');
        Route::get('analytics/topics', AgentTopicController::class)->name('analytics.topics');
        Route::get('analytics/topics/data', AgentTopicsDataController::class)->middleware('capability:topics')->name('topics.data');

        // Emergent (discovered) topic suggestions — surfaced from the consolidated
        // analysis pass; promotion turns one into a real, agent-attached topic.
        Route::get('analytics/topics/suggestions', SuggestedTopicsDataController::class)->middleware('capability:topics')->name('topics.suggestions');
        Route::middleware(['capability:topics', 'agent.permission:agent.topics.manage'])->group(function (): void {
            Route::post('analytics/topics/suggestions/promote', PromoteSuggestedTopicController::class)->name('topics.suggestions.promote');
            Route::delete('analytics/topics/suggestions', DismissSuggestedTopicController::class)->name('topics.suggestions.dismiss');
        });
        Route::get('analytics/sources', AgentSourceController::class)->name('analytics.sources');
        Route::get('analytics/sources/data', AgentSourcesDataController::class)->name('analytics.sources.data');

        // Topics Management — the index page is allowed through so the Vue
        // LockedFeature card can render on Starter/Standard; the mutations
        // are hard-gated.
        Route::get('topics', [TopicsResourceController::class, 'index'])
            ->middleware('agent.permission:agent.topics.manage')->name('topics.index');
        Route::middleware(['capability:topics', 'agent.permission:agent.topics.manage'])->group(function (): void {
            Route::post('topics', [TopicsResourceController::class, 'store'])->name('topics.store');
            Route::patch('topics/{topic}', [TopicsResourceController::class, 'update'])->name('topics.update');
            Route::delete('topics/{topic}', [TopicsResourceController::class, 'destroy'])->name('topics.destroy');
        });

        // Update Agent (Persona/Instructions)
        Route::patch('/update', [AgentController::class, 'update'])->name('update');

        Route::get('widget/preview', WidgetPreviewController::class)->name('widget.preview');
        Route::get('bar/preview', BarPreviewController::class)->name('bar.preview');

        // Playground — a single page with an in-page surface toggle (Hosted Chat
        // / Widget / Bar). Widget and Bar previews render the agent embedded in
        // a real-looking host page so the admin sees the real customer-facing
        // experience, not a configurator chrome.
        Route::get('playground', AgentPlaygroundController::class)->name('playground');

        Route::get('persona', AgentShowPersonaController::class)->name('persona');

        // Knowledge
        Route::get('sources', AgentKnowledgeSourceController::class)->name('sources');
        Route::get('sources/stats', AgentKnowledgeSourcesStatsController::class)->name('sources.stats');
        Route::get('sources/files', AgentKnowledgeFileController::class)->name('sources.files');
        Route::get('sources/files/data', AgentSourceFileController::class)->name('sources.files.data');
        Route::get('sources/markdown', AgentKnowledgeMarkdownController::class)->name('sources.markdown');
        Route::get('sources/audio', AgentSourceAudioController::class)->name('sources.audio');
        Route::get('sources/video', AgentSourceVideoController::class)->name('sources.video');
        Route::get('sources/web', AgentKnowledgeWebController::class)->name('sources.web');
        Route::get('sources/youtube', [AgentYoutubeController::class, 'index'])->name('sources.youtube.index');
        Route::post('sources/youtube', [AgentYoutubeController::class, 'store'])->name('sources.youtube.store');
        Route::delete('sources/youtube/{youtube}', [AgentYoutubeController::class, 'destroy'])->name('sources.youtube.destroy');
        Route::post('sources/youtube/{youtube}/retrain', RetrainAgentYoutubeController::class)->name('sources.youtube.retrain');

        Route::get('sources/text', [AgentTextController::class, 'index'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.index');
        Route::post('sources/text', [AgentTextController::class, 'store'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.store');
        Route::get('sources/text/{id}', [AgentTextController::class, 'show'])->name('sources.text.show');
        Route::patch('sources/text/{id}', [AgentTextController::class, 'update'])->name('sources.text.update');
        Route::post('sources/text/{id}/unlink', DetachAgentTextController::class)->name('sources.text.unlink');

        Route::get('sources/qa', [AgentQaController::class, 'index'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.index');
        Route::post('sources/qa', [AgentQaController::class, 'store'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.store');
        Route::get('sources/qa/{id}', [AgentQaController::class, 'show'])->name('sources.qa.show');
        Route::patch('sources/qa/{id}', [AgentQaController::class, 'update'])->name('sources.qa.update');
        Route::delete('sources/qa/{id}', [AgentQaController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.qa.destroy');
        Route::post('sources/qa/{id}/unlink', DetachAgentQaController::class)->name('sources.qa.unlink');

        // Functions (LLM tool calls — webhook-backed). Functions are owned at
        // the account level (Settings → Functions); on the agent screen the
        // user only attaches / detaches them. Attach = pivot exists; detach
        // = pivot deleted. No per-attachment enabled flag.
        Route::get('sources/functions', AgentFunctionController::class)->name('sources.functions.index');
        Route::get('sources/functions/data', AgentFunctionsListController::class)->name('sources.functions.data');
        Route::post('sources/functions/{function}/attach', AgentFunctionAttachController::class)->name('sources.functions.attach');
        Route::delete('sources/functions/{function}', AgentFunctionDeleteController::class)->name('sources.functions.detach');
        Route::post('sources/functions/{function}/test', AgentFunctionTestController::class)->name('sources.functions.test');

        // Connectors page — agents attach existing account-level connectors
        // via the pivot. Creation/deletion of connector rows happens in
        // /settings/connectors. Attach = pivot exists; detach = pivot deleted.
        Route::get('sources/connectors', AgentConnectorController::class)->name('sources.connectors.index');
        Route::get('sources/connectors/data', AgentConnectorsListController::class)->name('sources.connectors.data');
        Route::post('sources/connectors/{slug}/attach', AgentConnectorAttachController::class)->name('sources.connectors.attach');
        Route::delete('sources/connectors/{slug}', AgentConnectorDisconnectController::class)->name('sources.connectors.detach');
        Route::get('sources/connectors/{slug}/tools', AgentConnectorToolController::class)->name('sources.connectors.tools');
        Route::post('sources/connectors/{slug}/refresh', AgentConnectorRefreshController::class)->name('sources.connectors.refresh');

        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->name('sources.files.upload');
        Route::delete('sources/text/{id}', [AgentTextController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentSourceFileDeleteController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.files.delete');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->middleware('agent.permission:agent.sources.train.files')->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->name('agent.files.summary');

        Route::get('sources/websites', [AgentWebsiteController::class, 'index'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.index');
        Route::post('sources/websites', [AgentWebsiteController::class, 'store'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.store');
        Route::delete('sources/websites/{website}', [AgentWebsiteController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.websites.destroy');
        Route::post('sources/websites/{website}/train', TrainAgentWebsiteController::class)->name('sources.websites.train');

        Route::get('sources/websites/{website}/links', [AgentWebsiteLinkController::class, 'index'])->name('sources.websites.links.index');
        Route::delete('sources/websites/{website}/links/{link}', [AgentWebsiteLinkController::class, 'destroy'])->name('sources.websites.links.destroy');
        Route::post('sources/websites/{website}/links/{link}/retrain', RetrainAgentWebsiteLinkController::class)->name('sources.websites.links.retrain');
        Route::post('sources/websites/{website}/links/bulk-destroy', BulkDestroyAgentWebsiteLinksController::class)->name('sources.websites.links.bulk-destroy');

        // Starters API endpoint (used by Widget and Embed components)
        Route::put('starters', [AgentStarterController::class, 'update'])->name('starters.update');

        Route::get('leads', [AgentLeadController::class, 'index'])->middleware('agent.permission:agent.leads.view')->name('leads.index');
        Route::delete('leads', [AgentLeadController::class, 'destroy'])->middleware('agent.permission:agent.leads.view')->name('leads.destroy');
        Route::patch('leads/settings', AgentLeadSettingsController::class)->middleware('agent.permission:agent.leads.settings')->name('leads.settings');
        Route::post('leads/export', AgentLeadsExportController::class)->middleware('agent.permission:agent.leads.export')->name('leads.export');

        // Chatter members. index lists every workspace chatter (with a
        // `has_access` flag for this agent); store invites a brand-new
        // chatter; grant gives an existing chatter access to this agent;
        // destroy revokes this agent's grant (user record stays).
        Route::middleware('agent.permission:agent.members.manage')->group(function (): void {
            Route::get('members', [AgentMemberController::class, 'index'])->name('members.index');
            Route::post('members', [AgentMemberController::class, 'store'])->name('members.store');
            Route::post('members/{chatter}/grant', [AgentMemberController::class, 'grant'])->name('members.grant');
            Route::delete('members/{chatter}', [AgentMemberController::class, 'destroy'])->name('members.destroy');
        });

        Route::get('logs', [AgentChatLogController::class, 'index'])->middleware('agent.permission:agent.chat.view')->name('logs');
        Route::get('logs/conversations', [ChatLogConversationsController::class, 'index'])->middleware('agent.permission:agent.chat.view')->name('logs.conversations');
        Route::get('logs/messages/{conversation}', ChatLogConversationMessagesController::class)->middleware('agent.permission:agent.chat.view')->name('logs.messages');
        Route::get('logs/conversations/{conversation}/pulse', ChatLogConversationPulseController::class)->middleware(['capability:pulse', 'agent.permission:agent.chat.pulse'])->name('logs.pulse');
        Route::get('logs/topics', ChatLogTopicsController::class)->middleware('agent.permission:agent.chat.view')->name('logs.topics');
        Route::get('logs/countries', ChatLogCountriesController::class)->middleware('agent.permission:agent.chat.view')->name('logs.countries');
        Route::delete('logs/conversations/{conversation}', [ChatLogConversationsController::class, 'destroy'])->middleware('agent.permission:agent.chat.delete')->name('logs.delete-conversation');
        Route::post('logs/revise-answer', AgentChatLogReviseAnswerController::class)->middleware('agent.permission:agent.chat.view')->name('logs.revise-answer');

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

        // Custom domain (Hosted Chat) — pass an empty value to clear.
        // Quota gating happens in the controller.
        Route::patch('domain', UpdateAgentDomainController::class)->name('domain.update');

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

        // Rename Agent
        Route::patch('rename', RenameAgentController::class)->name('rename');

        // Linking / Unlinking Source Files
        Route::get('sources/trained-files', [AgentTrainedFileController::class, 'index'])->name('sources.list');
        Route::post('sources/trained-files/{fileId}/link', [AgentTrainedFileController::class, 'store'])->name('sources.files.link');
        Route::post('sources/trained-files/{file}/unlink', [AgentTrainedFileController::class, 'destroy'])->name('sources.files.unlink');

        // Text Sources
        Route::get('sources/trained-texts', AgentSourcesTrainedTextsListController::class)->name('sources.texts.list');
        Route::post('sources/trained-texts/{textId}/link', AgentSourcesLinkTextController::class)->name('sources.texts.link');
        Route::post('sources/trained-texts/{textId}/unlink', AgentSourceUnlinkTextController::class)->name('sources.texts.unlink');

        // Website Sources (cross-agent shared pool)
        Route::get('sources/trained-websites', [AgentTrainedWebsiteController::class, 'index'])->name('sources.trained-websites.index');
        Route::post('sources/trained-websites/{websiteId}/link', [AgentTrainedWebsiteController::class, 'store'])->name('sources.trained-websites.link');
        Route::post('sources/trained-websites/{websiteId}/unlink', [AgentTrainedWebsiteController::class, 'destroy'])->name('sources.trained-websites.unlink');

        // Delete Agent
        Route::delete('/', [AgentController::class, 'destroy'])->name('delete');

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
