<?php

namespace App\Http\Controllers\Agents\ChatLog;

use Domain\Agents\Actions\BuildConversationPulseAction;
use Domain\Agents\Data\Resources\ChatLog\ChatLogPulseResourceData;
use Domain\Agents\Models\Agent;
use Domain\Chat\Models\ChatConversation;
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
        ChatConversation $conversation,
        BuildConversationPulseAction $build,
    ): ChatLogPulseResourceData {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.chat.pulse'), 403);
        abort_if($conversation->session->agent_id !== $agent->id, 404);

        return $build->execute($agent, $conversation);
    }
}
