<?php

namespace Domain\Chat\Actions;

use App\Widget\WidgetVerification;
use Domain\Agents\Models\Agent;
use Domain\Analytics\Services\UserAgentParser;
use Domain\Chat\Data\InitializedWidgetSessionData;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\Memory\ChatMemory;

class InitializeWidgetSessionAction
{
    public function __construct(
        private readonly ChatMemory $memory,
        private readonly UserAgentParser $userAgentParser,
        private readonly WidgetVerification $verification,
    ) {}

    /**
     * @param  array<string, mixed>  $context  Carries ip, user_agent, locale, referer, page_url.
     */
    public function execute(
        Agent $agent,
        ?string $existingSessionId,
        ?string $existingThreadId,
        array $context,
    ): InitializedWidgetSessionData {
        $isNewSession = false;
        $isNewThread = false;

        $session = null;
        if ($existingSessionId) {
            $session = $this->verification->verifySessionExternalId($existingSessionId);
            $session?->update(['last_activity' => now()]);
        }

        if (! $session) {
            $userAgent = $this->userAgentParser->parse($context['user_agent'] ?? null);
            $session = ChatSession::create([
                'agent_id' => $agent->id,
                'ip_address' => $context['ip'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'device_type' => $userAgent['device_type'],
                'browser' => $userAgent['browser'],
                'os' => $userAgent['os'],
                'locale' => isset($context['accept_language']) ? substr($context['accept_language'], 0, 2) : 'en',
                'referrer_url' => $context['referer'] ?? null,
                'page_url' => $context['page_url'] ?? $context['referer'] ?? null,
            ]);

            $isNewSession = true;
        }

        $thread = null;
        if ($existingThreadId && $session) {
            $thread = $this->verification->verifyThreadId($existingThreadId);
            $thread?->update(['last_activity_at' => now()]);
        }

        $threadExternalId = $thread?->external_id;

        if (! $thread) {
            $threadExternalId = $this->memory
                ->setAgent($agent)
                ->setExternalSessionId($session->external_id)
                ->createConversation('New Chat');

            $isNewThread = true;
        }

        return new InitializedWidgetSessionData(
            sessionId: $session->external_id,
            threadId: $threadExternalId,
            isNewSession: $isNewSession,
            isNewThread: $isNewThread,
        );
    }
}
