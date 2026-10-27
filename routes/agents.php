<?php

declare(strict_types=1);

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
use App\Http\Controllers\Agent\Audit\AgentAuditController;
use App\Http\Controllers\Agent\Bar\BarPreviewController;
use App\Http\Controllers\Agent\Capability\AgentCapabilitiesController;
use App\Http\Controllers\Agent\Capability\ChooseCapabilityProviderController;
use App\Http\Controllers\Agent\Capability\DisableAgentCapabilityController;
use App\Http\Controllers\Agent\Capability\EnableAgentCapabilityController;
use App\Http\Controllers\Agent\Channel\AgentChannelsController;
use App\Http\Controllers\Agent\Channel\AgentInstagramChannelController;
use App\Http\Controllers\Agent\Channel\AgentInstagramOAuthCallbackController;
use App\Http\Controllers\Agent\Channel\AgentInstagramOAuthRedirectController;
use App\Http\Controllers\Agent\Channel\AgentMessengerChannelController;
use App\Http\Controllers\Agent\Channel\AgentMessengerOAuthCallbackController;
use App\Http\Controllers\Agent\Channel\AgentMessengerOAuthRedirectController;
use App\Http\Controllers\Agent\Channel\AgentSlackChannelController;
use App\Http\Controllers\Agent\Channel\AgentTeamsChannelController;
use App\Http\Controllers\Agent\Channel\AgentTelegramChannelController;
use App\Http\Controllers\Agent\Channel\AgentWhatsappChannelController;
use App\Http\Controllers\Agent\Channel\UpdateAgentTelegramCommandsController;
use App\Http\Controllers\Agent\ChatLog\AgentChatLogController;
use App\Http\Controllers\Agent\ChatLog\AgentChatLogReviseAnswerController;
use App\Http\Controllers\Agent\ChatLog\ConversationController as ChatLogConversationsController;
use App\Http\Controllers\Agent\ChatLog\ConversationMessageController as ChatLogConversationMessagesController;
use App\Http\Controllers\Agent\ChatLog\ConversationPulseController as ChatLogConversationPulseController;
use App\Http\Controllers\Agent\ChatLog\CountryController as ChatLogCountriesController;
use App\Http\Controllers\Agent\ChatLog\TopicController as ChatLogTopicsController;
use App\Http\Controllers\Agent\Chatter\AgentChatterController;
use App\Http\Controllers\Agent\Chatter\GrantAgentChatterAccessController;
use App\Http\Controllers\Agent\Eval\AgentEvalCaseController;
use App\Http\Controllers\Agent\Eval\AgentEvalController;
use App\Http\Controllers\Agent\Eval\AgentEvalRunController;
use App\Http\Controllers\Agent\Eval\ClearAgentEvalRunsController;
use App\Http\Controllers\Agent\Eval\CreateEvalCasesFromKnowledgeController;
use App\Http\Controllers\Agent\Eval\RunAgentEvalController;
use App\Http\Controllers\Agent\Eval\UpdateEvalAutoRunController;
use App\Http\Controllers\Agent\Form\AgentFormController;
use App\Http\Controllers\Agent\Form\AgentSubmissionController;
use App\Http\Controllers\Agent\Form\ExportFormSubmissionsController;
use App\Http\Controllers\Agent\Form\RewriteFormDescriptionController;
use App\Http\Controllers\Agent\Guardrail\AgentGuardrailController;
use App\Http\Controllers\Agent\Guardrail\BlockGuardrailOffenderController;
use App\Http\Controllers\Agent\Guardrail\TestGuardrailMessageController;
use App\Http\Controllers\Agent\Handoff\AgentHandoffController;
use App\Http\Controllers\Agent\Handoff\HandoffTypingController;
use App\Http\Controllers\Agent\Handoff\MarkHandoffReadController;
use App\Http\Controllers\Agent\Handoff\ReturnHandoffController;
use App\Http\Controllers\Agent\Handoff\SendHandoffMessageController;
use App\Http\Controllers\Agent\Handoff\TakeoverHandoffController;
use App\Http\Controllers\Agent\Identity\AgentIdentityController;
use App\Http\Controllers\Agent\Identity\RegenerateAgentAuthSecretController;
use App\Http\Controllers\Agent\Lead\AgentLeadSettingsController;
use App\Http\Controllers\Agent\Persona\AgentShowPersonaController;
use App\Http\Controllers\Agent\Playground\AgentPlaygroundController;
use App\Http\Controllers\Agent\RenameAgentController;
use App\Http\Controllers\Agent\Security\AgentBlockedController;
use App\Http\Controllers\Agent\Security\AgentRateLimitController;
use App\Http\Controllers\Agent\Security\AgentSecurityUpdateController;
use App\Http\Controllers\Agent\Security\ListAgentBlocksController;
use App\Http\Controllers\Agent\Security\UpdateAgentSecuritySettingsController;
use App\Http\Controllers\Agent\Settings\AgentSettingsController;
use App\Http\Controllers\Agent\Settings\AppearanceTextController;
use App\Http\Controllers\Agent\Settings\UpdateAppearanceTranslationsController;
use App\Http\Controllers\Agent\Setup\AgentDeployController;
use App\Http\Controllers\Agent\Setup\SetupController;
use App\Http\Controllers\Agent\Setup\UpdateAgentDomainController;
use App\Http\Controllers\Agent\Setup\UpdateAgentLlmController;
use App\Http\Controllers\Agent\Setup\VerifyAgentDomainController;
use App\Http\Controllers\Agent\Slug\GenerateAgentSlugController;
use App\Http\Controllers\Agent\Source\Audio\AgentSourceAudioController;
use App\Http\Controllers\Agent\Source\Connector\AgentConnectorAlwaysAvailableController;
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
use App\Http\Controllers\Agent\Source\File\Manage\ReplaceAgentSourceFileController;
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
use App\Http\Controllers\Agent\Source\Qa\AgentQaController;
use App\Http\Controllers\Agent\Source\Text\AgentSourcesLinkTextController;
use App\Http\Controllers\Agent\Source\Text\AgentSourcesTrainedTextsListController;
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
use App\Http\Controllers\Agent\Starters\GenerateStartersController;
use App\Http\Controllers\Agent\Topic\AgentTopicController as TopicsResourceController;
use App\Http\Controllers\Agent\Widget\AgentBubbleController;
use App\Http\Controllers\Agent\Widget\WidgetPreviewController;
use App\Http\Controllers\Countries\CountriesListController;
use App\Http\Middleware\Access\EnsureFeatureFlagIsOn;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can.access.dashboard', 'workspace.scope'])->prefix('my/')->group(function (): void {
    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentController::class, 'store'])->name('agents.store');

    Route::get('countries/list', CountriesListController::class)->name('countries.list');

    // Meta (Messenger/Instagram) channel OAuth callbacks. The Meta app whitelists a
    // single fixed redirect URI, so they sit outside the `agents/{agent}` group —
    // the agent rides in the OAuth `state` held in the session, not the URL.
    Route::get('channels/messenger/oauth/callback', AgentMessengerOAuthCallbackController::class)->middleware('throttle:30,1,meta-oauth-callback')->name('channels.messenger.callback');
    Route::get('channels/instagram/oauth/callback', AgentInstagramOAuthCallbackController::class)->middleware('throttle:30,1,meta-oauth-callback')->name('channels.instagram.callback');

    Route::prefix('agents/{agent}')->name('agents.')->middleware('can.access.agent')->group(function (): void {
        Route::get('home', AgentManagerController::class)->name('manager');
        // Analytics pages and their data — each gated by its own analytics
        // permission, the same one the agent menu uses to show the page.
        Route::middleware('agent.permission:agent.analytics.chats')->group(function (): void {
            Route::get('analytics', AgentAnalyticsController::class)->name('analytics');
            Route::get('analytics/data', AgentAnalyticsDataController::class)->name('analytics.data');
            Route::get('analytics/chart-data', AgentChartDataController::class)->name('analytics.chart-data');
        });
        Route::middleware('agent.permission:agent.analytics.sentiment')->group(function (): void {
            Route::get('analytics/sentiment', AgentSentimentController::class)->name('analytics.sentiment');
            Route::get('analytics/sentiment/data', AgentSentimentDataController::class)->name('sentiment.data');
        });
        Route::middleware('agent.permission:agent.analytics.tone')->group(function (): void {
            Route::get('analytics/tone', AgentToneController::class)->name('analytics.tone');
            Route::get('analytics/tone/data', AgentToneDataController::class)->name('tone.data');
        });
        Route::middleware('agent.permission:agent.analytics.topics')->group(function (): void {
            Route::get('analytics/topics', AgentTopicController::class)->name('analytics.topics');
            Route::get('analytics/topics/data', AgentTopicsDataController::class)->name('topics.data');

            // Emergent (discovered) topic suggestions — surfaced from the consolidated
            // analysis pass; promotion turns one into a real, agent-attached topic.
            Route::get('analytics/topics/suggestions', SuggestedTopicsDataController::class)->name('topics.suggestions');
        });
        Route::middleware('agent.permission:agent.topics.manage')->group(function (): void {
            Route::post('analytics/topics/suggestions/promote', PromoteSuggestedTopicController::class)->name('topics.suggestions.promote');
            Route::delete('analytics/topics/suggestions', DismissSuggestedTopicController::class)->name('topics.suggestions.dismiss');
        });
        Route::middleware('agent.permission:agent.analytics.sources')->group(function (): void {
            Route::get('analytics/sources', AgentSourceController::class)->name('analytics.sources');
            Route::get('analytics/sources/data', AgentSourcesDataController::class)->name('analytics.sources.data');
        });

        // Topics Management.
        Route::get('topics', [TopicsResourceController::class, 'index'])
            ->middleware('agent.permission:agent.topics.manage')->name('topics.index');
        Route::middleware('agent.permission:agent.topics.manage')->group(function (): void {
            Route::post('topics', [TopicsResourceController::class, 'store'])->name('topics.store');
            Route::patch('topics/{topic}', [TopicsResourceController::class, 'update'])->name('topics.update');
            Route::delete('topics/{topic}', [TopicsResourceController::class, 'destroy'])->name('topics.destroy');
        });

        // Update Agent (Persona/Instructions)
        Route::patch('/update', [AgentController::class, 'update'])
            ->middleware('agent.permission:agent.setup')->name('update');

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
        Route::get('sources/files', AgentKnowledgeFileController::class)->middleware('agent.permission:agent.sources.train.files')->name('sources.files');
        Route::get('sources/files/data', AgentSourceFileController::class)->middleware('agent.permission:agent.sources.train.files')->name('sources.files.data');
        Route::get('sources/markdown', AgentKnowledgeMarkdownController::class)->middleware('agent.permission:agent.sources.train.files')->name('sources.markdown');
        Route::get('sources/audio', AgentSourceAudioController::class)->middleware('agent.permission:agent.sources.train.audio')->name('sources.audio');
        Route::get('sources/video', AgentSourceVideoController::class)->middleware('agent.permission:agent.sources.train.video')->name('sources.video');
        Route::get('sources/web', AgentKnowledgeWebController::class)->middleware('agent.permission:agent.sources.train.web')->name('sources.web');

        // YouTube sources are not finished (added videos are never ingested),
        // so they stay off unless FEATURE_YOUTUBE_SOURCES turns them on. Videos
        // are trained under the video permission.
        Route::middleware(EnsureFeatureFlagIsOn::class.':youtube_sources')->group(function (): void {
            Route::get('sources/youtube', [AgentYoutubeController::class, 'index'])->middleware('agent.permission:agent.sources.train.video')->name('sources.youtube.index');
            Route::post('sources/youtube', [AgentYoutubeController::class, 'store'])->middleware('agent.permission:agent.sources.train.video')->name('sources.youtube.store');
            Route::delete('sources/youtube/{youtube}', [AgentYoutubeController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.youtube.destroy');
            Route::post('sources/youtube/{youtube}/retrain', RetrainAgentYoutubeController::class)->middleware('agent.permission:agent.sources.train.video')->name('sources.youtube.retrain');
        });

        Route::get('sources/text', [AgentTextController::class, 'index'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.index');
        Route::post('sources/text', [AgentTextController::class, 'store'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.store');
        Route::get('sources/text/{text}', [AgentTextController::class, 'show'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.show');
        Route::patch('sources/text/{text}', [AgentTextController::class, 'update'])->middleware('agent.permission:agent.sources.train.text')->name('sources.text.update');
        Route::post('sources/text/{text}/unlink', DetachAgentTextController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.text.unlink');

        Route::get('sources/qa', [AgentQaController::class, 'index'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.index');
        Route::post('sources/qa', [AgentQaController::class, 'store'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.store');
        Route::get('sources/qa/{qa}', [AgentQaController::class, 'show'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.show');
        Route::patch('sources/qa/{qa}', [AgentQaController::class, 'update'])->middleware('agent.permission:agent.sources.train.qa')->name('sources.qa.update');
        Route::delete('sources/qa/{qa}', [AgentQaController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.qa.destroy');

        // Functions (LLM tool calls — webhook-backed). Functions are owned at
        // the account level (Settings → Functions); on the agent screen the
        // user only attaches / detaches them. Attach = pivot exists; detach
        // = pivot deleted. No per-attachment enabled flag.
        Route::get('sources/functions', AgentFunctionController::class)->name('sources.functions.index');
        Route::get('sources/functions/data', AgentFunctionsListController::class)->name('sources.functions.data');
        // Attaching/detaching a webhook tool changes what the agent can call —
        // gate the mutations on the documented function-training permission.
        Route::post('sources/functions/{function}/attach', AgentFunctionAttachController::class)
            ->middleware('agent.permission:agent.sources.train.functions')->name('sources.functions.attach');
        Route::delete('sources/functions/{function}', AgentFunctionDeleteController::class)
            ->middleware('agent.permission:agent.sources.train.functions')->name('sources.functions.detach');

        // Connectors page — agents attach existing account-level connectors
        // via the pivot. Creation/deletion of connector rows happens in
        // /settings/connectors. Attach = pivot exists; detach = pivot deleted.
        Route::get('sources/connectors', AgentConnectorController::class)->name('sources.connectors.index');
        Route::get('sources/connectors/data', AgentConnectorsListController::class)->name('sources.connectors.data');
        Route::post('sources/connectors/{slug}/attach', AgentConnectorAttachController::class)
            ->middleware('agent.permission:agent.sources.train.connectors')->name('sources.connectors.attach');
        Route::delete('sources/connectors/{slug}', AgentConnectorDisconnectController::class)
            ->middleware('agent.permission:agent.sources.train.connectors')->name('sources.connectors.detach');
        // "Always on": the connector's tools ship on every turn of this agent,
        // bypassing the relevance router, however many tools it has.
        Route::put('sources/connectors/{slug}/always-available', AgentConnectorAlwaysAvailableController::class)
            ->middleware('agent.permission:agent.sources.train.connectors')->name('sources.connectors.always-available');
        Route::get('sources/connectors/{slug}/tools', AgentConnectorToolController::class)->name('sources.connectors.tools');
        Route::post('sources/connectors/{slug}/refresh', AgentConnectorRefreshController::class)
            ->middleware('agent.permission:agent.sources.train.connectors')->name('sources.connectors.refresh');

        Route::post('sources/files/upload', AgentKnowledgeFileUploadController::class)->middleware(['agent.permission:agent.sources.train.files', 'throttle:120,1,knowledge-upload'])->name('sources.files.upload');
        Route::delete('sources/text/{text}', [AgentTextController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.text.destroy');
        Route::delete('sources/files/{file}', AgentSourceFileDeleteController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.files.delete');
        Route::post('sources/files/{file}/replace', ReplaceAgentSourceFileController::class)->middleware(['agent.permission:agent.sources.train.files', 'throttle:120,1,knowledge-upload'])->name('sources.files.replace');
        Route::delete('sources/files/uploaded/all', AgentKnowledgeUploadedFilesDeleteController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.uploaded-files');
        Route::post('sources/files/process', AgentKnowledgeFilesProcessController::class)->middleware(['agent.permission:agent.sources.train.files', 'throttle:20,1,knowledge-process'])->name('sources.files.process');
        Route::get('sources/files/{file}/summary', AgentFileSummaryController::class)->middleware('agent.permission:agent.sources.train.files')->name('agent.files.summary');

        Route::get('sources/websites', [AgentWebsiteController::class, 'index'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.index');
        Route::post('sources/websites', [AgentWebsiteController::class, 'store'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.store');
        Route::delete('sources/websites/{website}', [AgentWebsiteController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.websites.destroy');
        Route::patch('sources/websites/{website}', [AgentWebsiteController::class, 'update'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.update');
        Route::post('sources/websites/{website}/train', TrainAgentWebsiteController::class)->middleware(['agent.permission:agent.sources.train.web', 'throttle:10,1,website-train'])->name('sources.websites.train');

        Route::get('sources/websites/{website}/links', [AgentWebsiteLinkController::class, 'index'])->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.links.index');
        Route::delete('sources/websites/{website}/links/{link}', [AgentWebsiteLinkController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.websites.links.destroy');
        Route::post('sources/websites/{website}/links/{link}/retrain', RetrainAgentWebsiteLinkController::class)->middleware('agent.permission:agent.sources.train.web')->name('sources.websites.links.retrain');
        Route::post('sources/websites/{website}/links/bulk-destroy', BulkDestroyAgentWebsiteLinksController::class)->middleware('agent.permission:agent.sources.delete')->name('sources.websites.links.bulk-destroy');

        // Starters API endpoint (used by Widget and Embed components)
        Route::put('starters', [AgentStarterController::class, 'update'])
            ->middleware('agent.permission:agent.setup')->name('starters.update');

        // AI-suggest starters from the agent's knowledge base (Setup → Starters).
        Route::post('starters/generate', GenerateStartersController::class)
            ->middleware(['agent.permission:agent.setup', 'throttle:10,1,starters-generate'])->name('starters.generate');

        // Widget chat-bubble prompts (Setup → Chat Bubbles) — auto-translated.
        Route::put('bubbles', [AgentBubbleController::class, 'update'])
            ->middleware('agent.permission:agent.setup')->name('bubbles.update');

        // Submissions live under each form (Forms → a form → Submissions).
        // Field + CRM-webhook settings stay on the shared settings endpoint.
        // Permissions reuse the agent.leads.* set. Leads need a license key.
        Route::patch('leads/settings', AgentLeadSettingsController::class)->middleware(['agent.permission:agent.leads.settings', 'license.capabilities'])->name('leads.settings');
        Route::get('forms/{form}/submissions', [AgentSubmissionController::class, 'index'])->middleware(['agent.permission:agent.leads.view', 'license.capabilities'])->name('forms.submissions.index');
        Route::get('forms/{form}/submissions/export', ExportFormSubmissionsController::class)->middleware(['agent.permission:agent.leads.export', 'license.capabilities', 'throttle:10,1,submissions-export'])->name('forms.submissions.export');
        Route::delete('forms/{form}/submissions', [AgentSubmissionController::class, 'destroy'])->middleware(['agent.permission:agent.leads.settings', 'license.capabilities'])->name('forms.submissions.destroy');

        // Chatter members. index lists every workspace chatter (with a
        // `has_access` flag for this agent); store invites a brand-new
        // chatter; grant gives an existing chatter access to this agent;
        // destroy revokes this agent's grant (user record stays).
        Route::middleware('agent.permission:agent.chatters.manage')->group(function (): void {
            Route::get('chatters', [AgentChatterController::class, 'index'])->name('chatters.index');
            Route::post('chatters', [AgentChatterController::class, 'store'])->name('chatters.store');
            Route::post('chatters/{chatter:external_id}/grant', GrantAgentChatterAccessController::class)->withoutScopedBindings()->name('chatters.grant');
            Route::delete('chatters/{chatter:external_id}', [AgentChatterController::class, 'destroy'])->name('chatters.destroy');
        });

        Route::get('logs', [AgentChatLogController::class, 'index'])->middleware('agent.permission:agent.chat.view')->name('logs');
        Route::get('logs/conversations', [ChatLogConversationsController::class, 'index'])->middleware('agent.permission:agent.chat.view')->name('logs.conversations');
        Route::get('logs/messages/{conversation}', ChatLogConversationMessagesController::class)->middleware('agent.permission:agent.chat.view')->name('logs.messages');

        // Per-agent tool-call audit log (Governed MCP Gateway), scoped to this
        // agent. Read-only — no mutations.
        Route::get('audit', AgentAuditController::class)->middleware('agent.permission:agent.audit.view')->name('audit');

        // Per-agent Eval Suites. View lists cases + recent runs; manage edits
        // cases and triggers a run (async).
        Route::get('evals', AgentEvalController::class)->middleware('agent.permission:agent.evals.view')->name('evals');
        Route::get('evals/runs/{run}', [AgentEvalRunController::class, 'show'])->middleware('agent.permission:agent.evals.view')->name('evals.runs.show');
        Route::post('evals/runs', RunAgentEvalController::class)->middleware(['agent.permission:agent.evals.manage', 'throttle:6,1,eval-runs'])->name('evals.runs.store');
        Route::delete('evals/runs/{run}', [AgentEvalRunController::class, 'destroy'])->middleware('agent.permission:agent.evals.manage')->name('evals.runs.destroy');
        Route::delete('evals/runs', ClearAgentEvalRunsController::class)->middleware('agent.permission:agent.evals.manage')->name('evals.runs.clear');
        Route::post('evals/cases', [AgentEvalCaseController::class, 'store'])->middleware('agent.permission:agent.evals.manage')->name('evals.cases.store');
        Route::post('evals/cases/from-knowledge', CreateEvalCasesFromKnowledgeController::class)->middleware('agent.permission:agent.evals.manage')->name('evals.cases.from-knowledge');
        Route::put('evals/auto-run', UpdateEvalAutoRunController::class)->middleware('agent.permission:agent.evals.manage')->name('evals.auto-run');
        Route::patch('evals/cases/{case}', [AgentEvalCaseController::class, 'update'])->middleware('agent.permission:agent.evals.manage')->name('evals.cases.update');
        Route::delete('evals/cases/{case}', [AgentEvalCaseController::class, 'destroy'])->middleware('agent.permission:agent.evals.manage')->name('evals.cases.destroy');

        // Per-agent AI-safety Guardrails — config form + immutable trigger log.
        Route::get('guardrails', [AgentGuardrailController::class, 'show'])->middleware('agent.permission:agent.guardrails.view')->name('guardrails');
        Route::post('guardrails', [AgentGuardrailController::class, 'update'])->middleware('agent.permission:agent.guardrails.manage')->name('guardrails.update');
        // Try a sample message against the form's settings (runs the classifier, so rate limited).
        Route::post('guardrails/test', TestGuardrailMessageController::class)->middleware(['agent.permission:agent.guardrails.manage', 'throttle:20,1,guardrail-test'])->name('guardrails.test');
        // Block the chatter or visitor behind an abusive trigger; the request
        // checks the chatter or security permission that the block needs.
        Route::post('guardrails/events/{event}/block', BlockGuardrailOffenderController::class)->middleware('agent.permission:agent.guardrails.view')->name('guardrails.block');

        // Capabilities — first-party, no-auth chat enrichments (Interactive
        // Charts, …). A friendlier surface over the builtin-connector machinery;
        // enabling provisions + attaches in one toggle. Reuses the connector
        // permission so the gate matches its underlying attach/detach.
        Route::get('capabilities', AgentCapabilitiesController::class)->name('capabilities');

        // Forms — the inline-form builder. Reuses the connector permission for
        // mutations (same surface family as Capabilities). Capabilities and
        // forms need a license key (`license.capabilities`); the two pages
        // still open and explain the lock, and turning one off stays allowed.
        Route::get('forms', [AgentFormController::class, 'index'])->name('forms.index');
        Route::post('forms', [AgentFormController::class, 'store'])
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities'])->name('forms.store');
        Route::post('forms/rewrite-description', RewriteFormDescriptionController::class)
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities', 'throttle:10,1,form-rewrite'])->name('forms.rewrite-description');
        Route::put('forms/{form}', [AgentFormController::class, 'update'])
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities'])->name('forms.update');
        Route::delete('forms/{form}', [AgentFormController::class, 'destroy'])
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities'])->name('forms.destroy');
        Route::post('capabilities/{slug}/enable', EnableAgentCapabilityController::class)
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities'])->name('capabilities.enable');
        Route::delete('capabilities/{slug}', DisableAgentCapabilityController::class)
            ->middleware('agent.permission:agent.sources.train.connectors')->name('capabilities.disable');
        Route::put('capabilities/{slug}/provider', ChooseCapabilityProviderController::class)
            ->middleware(['agent.permission:agent.sources.train.connectors', 'license.capabilities'])->name('capabilities.provider');

        // Channels — connecting the agent to messaging apps. Gated by its own
        // `agent.channels` permission (split out from `agent.setup`).
        Route::get('channels', AgentChannelsController::class)->middleware('agent.permission:agent.channels')->name('channels');
        Route::post('channels/telegram', [AgentTelegramChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.telegram.connect');
        Route::delete('channels/telegram', [AgentTelegramChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.telegram.disconnect');
        Route::put('channels/telegram/commands', UpdateAgentTelegramCommandsController::class)->middleware('agent.permission:agent.channels')->name('channels.telegram.commands');
        Route::post('channels/slack', [AgentSlackChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.slack.connect');
        Route::delete('channels/slack', [AgentSlackChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.slack.disconnect');
        // Messenger connects via Facebook Login for Business (one Chatvia Meta
        // app): redirect → Facebook consent → callback (outside this {agent}
        // group, see below) → select the Page. Disconnect is a single delete.
        Route::get('channels/messenger/oauth/redirect', AgentMessengerOAuthRedirectController::class)->middleware(['agent.permission:agent.channels', 'throttle:10,1,meta-oauth-start'])->name('channels.messenger.redirect');
        Route::post('channels/messenger/oauth/select', [AgentMessengerChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.messenger.select');
        Route::delete('channels/messenger', [AgentMessengerChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.messenger.disconnect');
        Route::post('channels/whatsapp', [AgentWhatsappChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.whatsapp.connect');
        Route::delete('channels/whatsapp', [AgentWhatsappChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.whatsapp.disconnect');
        Route::post('channels/msteams', [AgentTeamsChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.msteams.connect');
        Route::delete('channels/msteams', [AgentTeamsChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.msteams.disconnect');
        // Instagram connects via Facebook Login for Business (one Chatvia Meta
        // app): redirect → Facebook consent → callback (outside this {agent}
        // group, see below) → select the account. Disconnect is a single delete.
        Route::get('channels/instagram/oauth/redirect', AgentInstagramOAuthRedirectController::class)->middleware(['agent.permission:agent.channels', 'throttle:10,1,meta-oauth-start'])->name('channels.instagram.redirect');
        Route::post('channels/instagram/oauth/select', [AgentInstagramChannelController::class, 'store'])->middleware('agent.permission:agent.channels')->name('channels.instagram.select');
        Route::delete('channels/instagram', [AgentInstagramChannelController::class, 'destroy'])->middleware('agent.permission:agent.channels')->name('channels.instagram.disconnect');
        Route::get('logs/conversations/{conversation}/pulse', ChatLogConversationPulseController::class)->middleware('agent.permission:agent.chat.pulse')->name('logs.pulse');
        Route::get('logs/topics', ChatLogTopicsController::class)->middleware('agent.permission:agent.chat.view')->name('logs.topics');
        Route::get('logs/countries', ChatLogCountriesController::class)->middleware('agent.permission:agent.chat.view')->name('logs.countries');
        Route::delete('logs/conversations/{conversation}', [ChatLogConversationsController::class, 'destroy'])->middleware('agent.permission:agent.chat.delete')->name('logs.delete-conversation');
        // Revising an answer writes a Q&A entry, so it also needs the Q&A training permission.
        Route::post('logs/revise-answer', AgentChatLogReviseAnswerController::class)
            ->middleware(['agent.permission:agent.chat.view', 'agent.permission:agent.sources.train.qa'])
            ->name('logs.revise-answer');

        // Handoff (Admin Takeover) routes. Taking a chat over needs a license
        // key (`license.handoff`); the rest stay open so a held chat finishes.
        Route::get('handoff/pending', [AgentHandoffController::class, 'index'])->middleware('agent.permission:agent.chat.view')->name('handoff.pending');
        Route::post('handoff/{conversation}/takeover', TakeoverHandoffController::class)->middleware(['agent.permission:agent.chat.takeover', 'license.handoff'])->name('handoff.takeover');
        Route::post('handoff/{conversation}/return', ReturnHandoffController::class)->name('handoff.return');
        Route::post('handoff/{conversation}/message', SendHandoffMessageController::class)->name('handoff.message');
        Route::post('handoff/{conversation}/typing', HandoffTypingController::class)->name('handoff.typing');
        Route::post('handoff/{conversation}/mark-read', MarkHandoffReadController::class)->middleware('agent.permission:agent.chat.view')->name('handoff.mark-read');

        // Setup — one unified configuration page for every agent. All content
        // settings (text / persona / starters / model / branding) are shared
        // across the three surfaces; the top tab on the page is a preview
        // switcher, not a separate config. Surface-specific Behavior + Embed
        // fields swap on the active preview tab and write to their own
        // settings groups (widget / bar / embed).
        Route::get('setup', SetupController::class)->name('setup');

        // Deploy — embed/install hub (Widget, Hosted Chat, Command Bar snippets).
        Route::get('deploy', AgentDeployController::class)->middleware('agent.permission:agent.setup')->name('deploy');

        // Setup → Model: the model the agent answers with (one the admin
        // made available) and its answer temperature.
        Route::patch('llm', UpdateAgentLlmController::class)
            ->middleware('agent.permission:agent.setup')->name('llm.update');

        // Custom domain (Hosted Chat) — pass an empty value to clear.
        Route::patch('domain', UpdateAgentDomainController::class)
            ->middleware('agent.permission:agent.setup')->name('domain.update');
        // Turns a pending domain on once its TXT record proves the workspace
        // controls it. Each try is a DNS lookup, so it is throttled.
        Route::post('domain/verify', VerifyAgentDomainController::class)
            ->middleware(['agent.permission:agent.setup', 'throttle:10,1,domain-verify'])->name('domain.verify');

        // Toggling `is_public` exposes/locks the agent on the public internet —
        // gate it on the documented `agent.setup` permission, not mere access.
        Route::patch('security', AgentSecurityUpdateController::class)
            ->middleware('agent.permission:agent.setup')->name('security.update');

        // Per-agent signing secret. Owner mints sign-in tokens (chat ?token= and
        // widget data-auth) on their backend; regenerating invalidates every
        // token previously signed for this agent — so the destructive POST must
        // require the same permission the (read) GET above already enforces.
        Route::get('identity', AgentIdentityController::class)
            ->middleware('agent.permission:agent.identity.manage')->name('identity');
        Route::post('identity/regenerate', RegenerateAgentAuthSecretController::class)
            ->middleware('agent.permission:agent.identity.manage')->name('identity.regenerate');

        // Per-agent chat rate-limit — its own page under Security, saved via the
        // settings endpoint below.
        Route::get('security/rate-limiting', AgentRateLimitController::class)
            ->middleware('agent.permission:agent.identity.manage')->name('security.rate-limit');
        Route::post('security/settings', UpdateAgentSecuritySettingsController::class)
            ->middleware('agent.permission:agent.identity.manage')->name('security.settings.update');

        // Who cannot chat with the agent (blocked visitors, switched-off
        // chatters): a page under Security, and the JSON both it and the
        // Guardrails "Blocked" tab load.
        Route::get('security/blocked', AgentBlockedController::class)
            ->middleware('agent.permission:agent.guardrails.view')->name('security.blocked');
        Route::get('security/blocked/list', ListAgentBlocksController::class)
            ->middleware('agent.permission:agent.guardrails.view')->name('security.blocked.list');

        // Appearance text (chat title / welcome / composer placeholder). Saving
        // auto-translates the three strings into every supported language; owners
        // can then hand-edit any language (stored as overrides that win over the
        // AI output). Both gate on agent.setup like the generic settings endpoint.
        Route::post('appearance/text', AppearanceTextController::class)
            ->middleware(['agent.permission:agent.setup', 'throttle:10,1,appearance-text'])->name('appearance.text');
        Route::put('appearance/translations', UpdateAppearanceTranslationsController::class)
            ->middleware('agent.permission:agent.setup')->name('appearance.translations.update');

        // Settings (persona / branding / widget / bar / embed / lead-capture …)
        Route::post('settings/{group}', [AgentSettingsController::class, 'update'])
            ->middleware('agent.permission:agent.setup')->name('settings.update');
        Route::delete('settings/{group}', [AgentSettingsController::class, 'destroy'])
            ->middleware('agent.permission:agent.setup')->name('settings.delete');

        // Members

        // Rename Agent (renaming is part of agent setup)
        Route::patch('rename', RenameAgentController::class)->middleware('agent.permission:agent.setup')->name('rename');

        // Linking / Unlinking Source Files
        Route::get('sources/trained-files', [AgentTrainedFileController::class, 'index'])->middleware('agent.permission:agent.sources.train.files')->name('sources.list');
        Route::post('sources/trained-files/{file}/link', [AgentTrainedFileController::class, 'store'])->middleware('agent.permission:agent.sources.train.files')->name('sources.files.link');
        Route::post('sources/trained-files/{file}/unlink', [AgentTrainedFileController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.files.unlink');

        // Text Sources
        Route::get('sources/trained-texts', AgentSourcesTrainedTextsListController::class)->middleware('agent.permission:agent.sources.train.text')->name('sources.texts.list');
        Route::post('sources/trained-texts/{text}/link', AgentSourcesLinkTextController::class)->middleware('agent.permission:agent.sources.train.text')->name('sources.texts.link');

        // Website Sources (cross-agent shared pool)
        Route::get('sources/trained-websites', [AgentTrainedWebsiteController::class, 'index'])->middleware('agent.permission:agent.sources.train.web')->name('sources.trained-websites.index');
        Route::post('sources/trained-websites/{websiteId}/link', [AgentTrainedWebsiteController::class, 'store'])->middleware('agent.permission:agent.sources.train.web')->name('sources.trained-websites.link');
        Route::post('sources/trained-websites/{websiteId}/unlink', [AgentTrainedWebsiteController::class, 'destroy'])->middleware('agent.permission:agent.sources.delete')->name('sources.trained-websites.unlink');

        // Delete Agent
        Route::delete('/', [AgentController::class, 'destroy'])->middleware('agent.permission:agent.delete')->name('delete');

    });

    Route::post('agents/knowledge/files/analyze', AgentKnowledgeFilesAnalyzeController::class)->middleware('throttle:120,1,knowledge-upload')->name('agents.knowledge.files.analyze');

    Route::get('generate-agent-slug', GenerateAgentSlugController::class)->name('generate-agent-slug');
});
