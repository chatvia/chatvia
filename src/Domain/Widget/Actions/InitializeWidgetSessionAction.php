<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * Bootstraps a widget visit: reuses the session and conversation the widget
 * remembered when they still belong to this agent (and, on a protected agent,
 * to the signed-in chatter), and starts new ones otherwise. The result tells
 * the widget which ids to send from now on and whether either one is new.
 *
 * The writes share one transaction, so a failure never leaves a session
 * without its conversation.
 */
class InitializeWidgetSessionAction
{
    /** Session language when the browser sends no Accept-Language header. */
    private const string DEFAULT_LOCALE = 'en';

    public function __construct(
        private readonly WidgetVerification $widgetVerification,
        private readonly UserAgentParser $userAgentParser,
        private readonly StartConversationAction $startConversation,
    ) {}

    public function execute(Agent $agent, WidgetSessionInitData $data, ?ChatViewerData $viewer = null): WidgetSessionInitResultData
    {
        return DB::transaction(fn (): WidgetSessionInitResultData => $this->initialize($agent, $data, $viewer));
    }

    private function initialize(Agent $agent, WidgetSessionInitData $data, ?ChatViewerData $viewer): WidgetSessionInitResultData
    {
        $session = $this->resumeSession($agent, $data, $viewer);
        $isNewSession = ! $session instanceof Session;
        $session ??= $this->startSession($agent, $data, $viewer);

        $conversationId = $this->resumeConversation($agent, $session, $data, $viewer)?->external_id;
        $isNewConversation = $conversationId === null;
        $conversationId ??= $this->startConversation->execute(
            new ChatScopeData($agent, $session->external_id),
            ConversationSource::Widget,
        )->external_id;

        return new WidgetSessionInitResultData(
            session_id: $session->external_id,
            conversation_id: $conversationId,
            is_new_session: $isNewSession,
            is_new_conversation: $isNewConversation,
        );
    }

    /**
     * The session the widget remembered, when it belongs to this agent (and,
     * on a protected agent, to this chatter). A session id from another agent
     * or another chatter is ignored, so a fresh session is started instead.
     */
    private function resumeSession(Agent $agent, WidgetSessionInitData $data, ?ChatViewerData $viewer): ?Session
    {
        if (! $data->existingSessionId) {
            return null;
        }

        $session = $this->widgetVerification->verifySessionExternalId($data->existingSessionId, $agent, $viewer);
        $session?->update(['last_activity_at' => now()]);

        return $session;
    }

    private function startSession(Agent $agent, WidgetSessionInitData $data, ?ChatViewerData $viewer): Session
    {
        $userAgent = $this->userAgentParser->detect($data->userAgent);

        return Session::create([
            'workspace_id' => $agent->workspace_id,
            'agent_id' => $agent->id,
            // Bound to the signed-in chatter (or staff preview) on a protected
            // agent (null for an anonymous visitor), so later reads and deletes
            // can be scoped to the owner instead of trusting the session id.
            'user_id' => $viewer instanceof ChatViewerData && ! $viewer->isChatter() ? $viewer->id : null,
            'chatter_id' => $viewer?->isChatter() ? $viewer->id : null,
            'ip_address' => $data->ipAddress,
            'user_agent' => $data->userAgent,
            'device_type' => $userAgent->deviceType,
            'browser' => $userAgent->browser,
            'os' => $userAgent->os,
            'locale' => $data->acceptLanguage ? Str::substr($data->acceptLanguage, 0, 2) : self::DEFAULT_LOCALE,
            // Visitor-supplied URLs keep only http(s), so a javascript: or
            // data: URI cannot run when an operator opens the link in the
            // conversation details panel.
            'referrer_url' => SafeUrl::httpSchemeOrNull($data->referrerUrl),
            'page_url' => SafeUrl::httpSchemeOrNull($data->pageUrl ?? $data->referrerUrl),
        ]);
    }

    /**
     * The conversation the widget remembered, under the same agent and chatter
     * scoping as the session: a conversation from another agent or chatter
     * never comes back here, even when its id is sent.
     *
     * It must also belong to the session this visit uses. The widget's
     * session-scoped endpoints refuse a conversation of another session, so
     * resuming one would hand back a thread the visitor cannot use. Its own
     * session is not adopted instead: a conversation id alone must not grant
     * access to every other conversation of that session.
     */
    private function resumeConversation(Agent $agent, Session $session, WidgetSessionInitData $data, ?ChatViewerData $viewer): ?Conversation
    {
        if (! $data->existingConversationId) {
            return null;
        }

        $conversation = $this->widgetVerification->verifyConversationId($data->existingConversationId, $agent, $viewer);

        if ($conversation?->chat_session_id !== $session->id) {
            return null;
        }

        $conversation->update(['last_activity_at' => now()]);

        return $conversation;
    }
}
