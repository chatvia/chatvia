<?php

namespace App\Http\Controllers\Agents\Handoff;

use Domain\Agents\Models\Agent;
use Domain\Chat\Actions\MarkHandoffConversationReadAction;
use Domain\Chat\Data\Handoff\HandoffMarkReadResultData;
use Domain\Chat\Models\ChatConversation;

class MarkHandoffReadController
{
    public function __invoke(
        Agent $agent,
        ChatConversation $conversation,
        MarkHandoffConversationReadAction $markRead,
    ): HandoffMarkReadResultData {
        $session = $conversation->session;

        abort_if(! $session || $session->agent_id !== $agent->id, 404);

        return new HandoffMarkReadResultData(
            marked_count: $markRead->execute($agent, $conversation),
        );
    }
}
