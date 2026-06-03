<?php

namespace App\Http\Controllers\Chat\Tools;

use App\Http\Requests\Chat\Tools\ResolveToolApprovalRequest;
use Domain\Conversation\Models\ToolApprovalCheckpoint;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Memory\TurnState;
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
        private TurnState $session,
    ) {}

    public function __invoke(ResolveToolApprovalRequest $request, ToolApprovalCheckpoint $approval): StreamedResponse
    {
        $agent = current_agent();

        // The checkpoint must belong to this agent and to the same chat session
        // that created it — a different visitor cannot resolve it.
        abort_unless(
            $agent !== null
            && $approval->agent_id === $agent->id
            && ($approval->context['external_session_id'] ?? null) === $this->session->firstOrCreateSession($agent),
            403,
        );

        return $this->chatStreamService->resume($approval, $request->toData());
    }
}
