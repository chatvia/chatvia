<?php

namespace App\Http\Controllers\Agent\Handoff;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\MarkHandoffConversationReadAction;
use Domain\Conversation\Data\Handoff\HandoffMarkReadResultData;
use Domain\Conversation\Models\Conversation;

class MarkHandoffReadController
{
    public function __invoke(
        Agent $agent,
        Conversation $conversation,
        MarkHandoffConversationReadAction $markRead,
    ): HandoffMarkReadResultData {
        $session = $conversation->session;

        abort_if(! $session || $session->agent_id !== $agent->id, 404);

        return new HandoffMarkReadResultData(
            marked_count: $markRead->execute($agent, $conversation),
        );
    }
}
