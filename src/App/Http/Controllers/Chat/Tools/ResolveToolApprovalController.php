<?php

namespace App\Http\Controllers\Chat\Tools;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\Tools\ResolveToolApprovalRequest;
use App\Http\Responses\SseResponder;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatTurnStreamer;
use Domain\Conversation\Services\Turn\RunningTurns;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Resume a paused agent turn after the chatter approved/denied the gated tool
 * call(s). The decisions reference stored calls by tool_call_id — the client
 * never supplies tool arguments — and the checkpoint resolves exactly once.
 * Like a fresh turn, the continuation runs to the end even when the browser
 * goes away, and counts as running while it does ({@see RunningTurns}).
 */
readonly class ResolveToolApprovalController
{
    public function __construct(
        private ChatTurnStreamer $chatTurnStreamer,
        private HostedChatSession $session,
        private SseResponder $sse,
        private RunningTurns $runningTurns,
    ) {}

    public function __invoke(
        ResolveToolApprovalRequest $request,
        ToolApprovalCheckpoint $approval,
        #[HostedChatAgent] ?Agent $agent,
    ): StreamedResponse {
        // The checkpoint must belong to this agent and to the same chat session
        // that created it. Anyone else's answers like an unknown checkpoint.
        abort_unless(
            $agent instanceof Agent
            && $approval->agent_id === $agent->id
            && $approval->approvalContext()->externalSessionId === $this->session->findOrStart($agent),
            404,
        );

        return $this->sse->respondUntilFinished($this->runningTurns->track(
            $approval->conversation_external_id,
            fn (): Generator => $this->chatTurnStreamer->resume($approval, $request->toData()),
        ));
    }
}
