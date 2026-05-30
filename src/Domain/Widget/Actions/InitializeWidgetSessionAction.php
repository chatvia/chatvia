<?php

namespace Domain\Widget\Actions;

use App\Widget\WidgetVerification;
use Domain\Agents\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Chat\Enums\ConversationSource;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Widget\Data\WidgetSessionInitResultData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Bootstrap a widget session: validate (or mint) the session, validate (or
 * mint) the thread, and return both identifiers along with flags that tell
 * the widget whether either was just created.
 */
class InitializeWidgetSessionAction
{
    public function __construct(
        private readonly WidgetVerification $widgetVerification,
        private readonly UserAgentParser $userAgentParser,
        private readonly ChatMemory $memory,
    ) {}

    public function execute(Agent $agent, Request $request): WidgetSessionInitResultData
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        $isNewSession = false;
        $isNewThread = false;

        $session = null;
        if ($sessionId) {
            // Agent-scoped: a session id from another agent is ignored, and a
            // fresh one is minted for this agent below.
            $session = $this->widgetVerification->verifySessionExternalId($sessionId, $agent);
            $session?->update(['last_activity' => now()]);
        }

        if (! $session) {
            $userAgent = $this->userAgentParser->parse($request->userAgent());
            $acceptLanguage = $request->header('Accept-Language');
            $session = ChatSession::create([
                'agent_id' => $agent->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $userAgent['device_type'],
                'browser' => $userAgent['browser'],
                'os' => $userAgent['os'],
                'locale' => $acceptLanguage ? Str::substr($acceptLanguage, 0, 2) : 'en',
                'referrer_url' => $request->header('Referer'),
                'page_url' => $request->input('page_url') ?? $request->header('Referer'),
            ]);
            $isNewSession = true;
        }

        $thread = null;
        if ($threadId) {
            // Same agent-scoping rule: a thread from another agent never
            // resurfaces here, even if the visitor (or attacker) sends it.
            $thread = $this->widgetVerification->verifyThreadId($threadId, $agent);
            $thread?->update(['last_activity_at' => now()]);
        }

        $threadExternalId = $thread?->external_id;

        if (! $thread) {
            $threadExternalId = $this->memory
                ->setAgent($agent)
                ->setExternalSessionId($session->external_id)
                ->createConversation('New Chat', ConversationSource::Widget);
            $isNewThread = true;
        }

        return new WidgetSessionInitResultData(
            session_id: $session->external_id,
            thread_id: $threadExternalId,
            is_new_session: $isNewSession,
            is_new_thread: $isNewThread,
        );
    }
}
