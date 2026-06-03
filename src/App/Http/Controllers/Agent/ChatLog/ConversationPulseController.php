<?php

namespace App\Http\Controllers\Agent\ChatLog;

use Domain\Agent\Actions\ChatLog\BuildConversationPulseAction;
use Domain\Agent\Data\Resources\ChatLog\Pulse\ChatLogPulseResourceData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;

/**
 * Per-conversation pulse endpoint — delegates the calculation entirely to
 * {@see BuildConversationPulseAction}.
 */
class ConversationPulseController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        BuildConversationPulseAction $build,
    ): ChatLogPulseResourceData {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.pulse'), 403);
        abort_if($conversation->session->agent_id !== $agent->id, 404);

        return $build->execute($agent, $conversation);
    }
}
