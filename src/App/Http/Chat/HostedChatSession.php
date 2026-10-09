<?php

namespace App\Http\Chat;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Sessions\StartChatSessionAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Services\Memory\ChatSessions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The hosted chat visitor's chat session, resolved for the current request.
 * The session id is remembered in the browser's Laravel session; a signed-in
 * user whose browser session was reset (signing out clears it) is reattached
 * to their latest session with the agent instead of getting a new one.
 *
 * This is the HTTP side of the domain's visitor sessions: it reads the browser
 * session and the web guard, and hands the domain plain ids
 * ({@see ChatScopeData}).
 */
final readonly class HostedChatSession
{
    private const string SESSION_KEY = 'cs:session';

    public function __construct(
        private ChatSessions $sessions,
        private StartChatSessionAction $startSession,
    ) {}

    /** The visitor's session with the agent, started when they have none. */
    public function findOrStart(Agent $agent): string
    {
        $sessionId = $this->find($agent) ?? $this->startSession->execute($agent, $this->viewerId());

        Session::put(self::SESSION_KEY, $sessionId);

        return $sessionId;
    }

    /**
     * The visitor's existing session with the agent, or null. Never starts
     * one, so read-only endpoints (like search) can use it.
     */
    public function find(Agent $agent): ?string
    {
        $remembered = Session::get(self::SESSION_KEY);

        if (is_string($remembered) && $this->sessions->isLive($agent, $remembered)) {
            return $remembered;
        }

        $viewerId = $this->viewerId();

        return $viewerId !== null ? $this->sessions->latestFor($agent, $viewerId) : null;
    }

    /**
     * The scope for chat-memory operations on the visitor's session (started
     * when needed), optionally narrowed to one conversation.
     */
    public function scope(Agent $agent, ?string $conversationId = null): ChatScopeData
    {
        return new ChatScopeData($agent, $this->findOrStart($agent), $conversationId, $this->viewerId());
    }

    /** The signed-in web user (any type), or null for a guest. */
    public function viewerId(): ?int
    {
        $id = Auth::guard('web')->id();

        return $id !== null ? (int) $id : null;
    }
}
