<?php

namespace App\Http\Controllers\Agent\Handoff;

use App\Http\Resources\Handoff\HandoffReturnResultData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Handoff\ReturnConversationToAiAction;
use Domain\Conversation\Models\Conversation;
use Illuminate\Http\Request;

/**
 * The operator who holds a conversation hands it back to the AI. Agent access
 * is checked by the route's `can.access.agent` middleware.
 */
class ReturnHandoffController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        Conversation $conversation,
        ReturnConversationToAiAction $returnToAi,
    ): HandoffReturnResultData {
        abort_unless($conversation->belongsToAgent($agent), 403, __('Unauthorized.'));

        abort_unless(
            $returnToAi->execute($conversation, $request->user()),
            409,
            __('Unable to return this conversation to AI.'),
        );

        return HandoffReturnResultData::returned();
    }
}
