<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Resources\Handoff\HandoffMarkReadResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Activity\MarkMessagesReadAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\MessageReader;
use Domain\Conversation\Models\Conversation;

/**
 * The operator read the visitor's messages in a handed-off conversation:
 * marks them read for the read receipts.
 */
class MarkHandoffReadController
{
    public function __invoke(
        Agent $agent,
        Conversation $conversation,
        MarkMessagesReadAction $markRead,
    ): HandoffMarkReadResultData {
        $session = $conversation->session;

        abort_if(! $session || $session->agent_id !== $agent->id, 404);

        return new HandoffMarkReadResultData(
            marked_count: $markRead->execute(
                new ChatScopeData($agent, $session->external_id, $conversation->external_id),
                MessageReader::Operator,
            ),
        );
    }
}
