<?php

namespace Domain\Widget\Actions;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Data\Session\WidgetSessionInitData;
use Domain\Widget\Data\Session\WidgetSessionInitResultData;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Str;

/**
 * Bootstrap a widget session: validate (or mint) the session, validate (or
 * mint) the conversation, and return both identifiers along with flags that
 * tell the widget whether either was just created.
 */
class InitializeWidgetSessionAction
{
    public function __construct(
        private readonly WidgetVerification $widgetVerification,
        private readonly UserAgentParser $userAgentParser,
        private readonly ChatMemory $memory,
    ) {}

    public function execute(Agent $agent, WidgetSessionInitData $data): WidgetSessionInitResultData
    {
        $isNewSession = false;
        $isNewConversation = false;

        $session = null;
        if ($data->existingSessionId) {
            // Agent-scoped: a session id from another agent is ignored, and a
            // fresh one is minted for this agent below.
            $session = $this->widgetVerification->verifySessionExternalId($data->existingSessionId, $agent);
            $session?->update(['last_activity_at' => now()]);
        }

        if (! $session) {
            $userAgent = $this->userAgentParser->parse($data->userAgent);
            $session = Session::create([
                'agent_id' => $agent->id,
                'ip_address' => $data->ipAddress,
                'user_agent' => $data->userAgent,
                'device_type' => $userAgent['device_type'],
                'browser' => $userAgent['browser'],
                'os' => $userAgent['os'],
                'locale' => $data->acceptLanguage ? Str::substr($data->acceptLanguage, 0, 2) : 'en',
                'referrer_url' => $data->referrerUrl,
                'page_url' => $data->pageUrl ?? $data->referrerUrl,
            ]);
            $isNewSession = true;
        }

        $conversation = null;
        if ($data->existingConversationId) {
            // Same agent-scoping rule: a conversation from another agent never
            // resurfaces here, even if the visitor (or attacker) sends it.
            $conversation = $this->widgetVerification->verifyConversationId($data->existingConversationId, $agent);
            $conversation?->update(['last_activity_at' => now()]);
        }

        $conversationExternalId = $conversation?->external_id;

        if (! $conversationExternalId) {
            $conversationExternalId = $this->memory
                ->setAgent($agent)
                ->setExternalSessionId($session->external_id)
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
