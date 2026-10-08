<?php

namespace Domain\Widget\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * Bootstrap a widget session: validate (or mint) the session, validate (or
 * mint) the conversation, and return both identifiers along with flags that
 * tell the widget whether either was just created. The writes share one
 * transaction, so a failure never leaves a session without its conversation.
 */
class InitializeWidgetSessionAction
{
    public function __construct(
        private readonly WidgetVerification $widgetVerification,
        private readonly UserAgentParser $userAgentParser,
        private readonly ChatMemory $memory,
    ) {}

    public function execute(Agent $agent, WidgetSessionInitData $data, ?User $authUser = null): WidgetSessionInitResultData
    {
        return DB::transaction(fn (): WidgetSessionInitResultData => $this->initialize($agent, $data, $authUser));
    }

    private function initialize(Agent $agent, WidgetSessionInitData $data, ?User $authUser): WidgetSessionInitResultData
    {
        $isNewSession = false;
        $isNewConversation = false;

        $session = null;
        if ($data->existingSessionId) {
            // Agent-scoped (and, on a protected agent, user-scoped): a session id
            // from another agent — or another chatter — is ignored, and a fresh
            // one is minted below bound to the authenticated user.
            $session = $this->widgetVerification->verifySessionExternalId($data->existingSessionId, $agent, $authUser);
            $session?->update(['last_activity_at' => now()]);
        }

        if (! $session) {
            $userAgent = $this->userAgentParser->parse($data->userAgent);
            $session = Session::create([
                'agent_id' => $agent->id,
                // Bind the session to the authenticated chatter on protected
                // agents (null for public/anonymous), so later reads/deletes can
                // be scoped to the owning user instead of trusting the session id.
                'user_id' => $authUser?->id,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'device_type' => $userAgent['device_type'],
                'browser' => $userAgent['browser'],
                'os' => $userAgent['os'],
                'locale' => $data->acceptLanguage ? Str::substr($data->acceptLanguage, 0, 2) : 'en',
                // Scheme-guard visitor-supplied URLs so a javascript:/data: URI
                // can't later execute when an operator clicks the link in the
                // admin Conversation Details panel.
                'referrer_url' => SafeUrl::httpSchemeOrNull($data->referrerUrl),
                'page_url' => SafeUrl::httpSchemeOrNull($data->pageUrl ?? $data->referrerUrl),
            ]);
            $isNewSession = true;
        }

        $conversation = null;
        if ($data->existingConversationId) {
            // Same agent- + user-scoping rule: a conversation from another agent
            // (or another chatter) never resurfaces here, even if the visitor
            // (or attacker) sends it.
            $conversation = $this->widgetVerification->verifyConversationId($data->existingConversationId, $agent, $authUser);
            $conversation?->update(['last_activity_at' => now()]);
        }

        $conversationExternalId = $conversation?->external_id;

        if (! $conversationExternalId) {
            $conversationExternalId = $this->memory
                ->withContext($agent, $session->external_id)
                ->createConversation('New Chat', ConversationSource::Widget);
            $isNewConversation = true;
        }

        return new WidgetSessionInitResultData(
            session_id: $session->external_id,
            conversation_id: $conversationExternalId,
            is_new_session: $isNewSession,
            is_new_conversation: $isNewConversation,
        );
    }
}
