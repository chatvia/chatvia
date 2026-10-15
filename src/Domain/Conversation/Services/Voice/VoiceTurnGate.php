<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use App\Identity\Workspace\Services\MessageAllowance;
use App\Identity\Workspace\Services\WorkspaceActivation;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\VoiceCallRefusal;
use Domain\Conversation\Services\AgentChatRateLimiter;
use Domain\Conversation\Services\ChatStreamService;

/**
 * The per-turn checks of a typed turn ({@see ChatStreamService}), for a voice
 * call: a deactivated workspace ({@see WorkspaceActivation}), the workspace's
 * monthly message limit, the chatter's own limit, and the agent's
 * messages-per-minute cap. Run when a call starts and before every spoken
 * reply, so a call still open when the workspace is deactivated hangs up at
 * the visitor's next turn.
 */
final readonly class VoiceTurnGate
{
    public function __construct(
        private MessageAllowance $allowance,
        private AgentChatRateLimiter $rateLimiter,
        private WorkspaceActivation $activation,
    ) {}

    /** Why the turn may not be answered, or null when it may. */
    public function refusal(Agent $agent, ?int $chatterId): ?VoiceCallRefusal
    {
        if (! $this->activation->isActive($agent->workspace_id)) {
            return VoiceCallRefusal::NotAvailable;
        }

        if (! $this->allowance->canSend((int) $agent->workspace_id)) {
            return VoiceCallRefusal::MessageLimitReached;
        }

        if ($chatterId !== null && ! $this->allowance->canSendAsChatter($chatterId)) {
            return VoiceCallRefusal::ChatterMessageLimitReached;
        }

        if ($this->rateLimiter->exceeded($agent)) {
            return VoiceCallRefusal::RateLimited;
        }

        return null;
    }
}
