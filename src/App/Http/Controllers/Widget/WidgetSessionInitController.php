<?php

namespace App\Http\Controllers\Widget;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\Memory\ChatMemory;
use Domain\Analytics\Services\UserAgentParser;
use App\Widget\WidgetVerification;
use Illuminate\Http\Request;

class WidgetSessionInitController
{
    public function __invoke(Request $request, WidgetVerification $widgetVerification, UserAgentParser $userAgentParser, ChatMemory $memory)
    {
        $agentId = $request->header('X-AGENT-ID');
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');

        if (! $widgetVerification->verifyByAgentExternalId($agentId, $request)) {
            return response()->json(data: ['error' => 'Could not verify the agent'], status: 403);
        }

        $agent = current_agent_from_widget();

        $isNewSession = false;
        $isNewThread = false;

        // Step 1: Validate or create session
        $session = null;

        if ($sessionId) {
            $session = $widgetVerification->verifySessionExternalId($sessionId);
            $session?->update(['last_activity' => now()]);
        }

        // Create new session if validation failed or no session provided
        if (! $session) {
            $userAgent = $userAgentParser->parse($request->userAgent());
            $session = ChatSession::create([
                'agent_id' => $agent->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_type' => $userAgent['device_type'],
                'browser' => $userAgent['browser'],
                'os' => $userAgent['os'],
                'locale' => $request->header('Accept-Language') ?
                    substr($request->header('Accept-Language'), 0, 2) : 'en',
                'referrer_url' => $request->header('Referer'),
                'page_url' => $request->input('page_url') ?? $request->header('Referer'),
            ]);

            $isNewSession = true;
        }

        $thread = null;

        if ($threadId && $session) {
            $thread = $widgetVerification->verifyThreadId($threadId);

            $thread?->update([
                'last_activity_at' => now(),
            ]);
        }

        if (! $thread) {
            // Use ChatMemory to create conversation so it's properly synced to cache
            $threadExternalId = $memory
                ->setAgent($agent)
                ->setExternalSessionId($session->external_id)
                ->createConversation('New Chat');

            $isNewThread = true;
        }

        return response()->json([
            'session_id' => $session->external_id,
            'thread_id' => $threadExternalId ?? $thread->external_id,
            'is_new_session' => $isNewSession,
            'is_new_thread' => $isNewThread,
        ]);
    }
}
