<?php

namespace App\Http\Controllers\Chat\Tools;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\Tools\ResolveToolApprovalRequest;
use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatStreamService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resume a paused agent turn after the chatter approved/denied the gated tool
 * call(s). The decisions reference stored calls by tool_call_id — the client
 * never supplies tool arguments — and the checkpoint resolves exactly once.
 */
readonly class ResolveToolApprovalController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private HostedChatSession $session,
        private SseResponder $sse,
    ) {}

    public function __invoke(
        ResolveToolApprovalRequest $request,
        ToolApprovalCheckpoint $approval,
        #[HostedChatAgent] ?Agent $agent,
    ): StreamedResponse {
        // The checkpoint must belong to this agent and to the same chat session
        // that created it — a different visitor cannot resolve it.
        abort_unless(
            $agent instanceof Agent
            && $approval->agent_id === $agent->id
            && $approval->approvalContext()->externalSessionId === $this->session->findOrStart($agent),
            403,
        );

        return $this->sse->respond($this->chatStreamService->resume($approval, $request->toData()));
    }
}
