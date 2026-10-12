<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Usage;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Services\MessageAllowance;
use Illuminate\Http\Request;

/**
 * Whether the visitor can still send messages this month — under the agent
 * workspace's limit and, for a signed-in chatter, the chatter's own — so the
 * hosted chat can warn before the visitor types.
 */
class MessagesUsage
{
    /**
     * @return array{can_send_messages: bool}
     */
    public function __invoke(Request $request, MessageAllowance $allowance): array
    {
        $account = $request->user();
        $chatterMaySend = ! $account instanceof Chatter || $allowance->canSendAsChatter($account->id);

        return [
            'can_send_messages' => $chatterMaySend && $allowance->canSend((int) $request->attributes->get('agent_workspace_id')),
        ];
    }
}
