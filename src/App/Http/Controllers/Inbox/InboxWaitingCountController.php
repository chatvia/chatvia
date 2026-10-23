<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbox;

use App\Http\Resources\Inbox\InboxWaitingCountData;
use App\Identity\Workspace\Models\User;
use Domain\Conversation\Queries\InboxConversationsQuery;
use Domain\Conversation\Services\Inbox\InboxAgents;
use Illuminate\Http\Request;

/**
 * How many chats wait for a human across the agents the signed-in person may
 * view: the badge on the Inbox link in the header.
 */
class InboxWaitingCountController
{
    public function __invoke(Request $request, InboxAgents $inboxAgents, InboxConversationsQuery $inbox): InboxWaitingCountData
    {
        /** @var User $user */
        $user = $request->user();

        return new InboxWaitingCountData($inbox->waitingCount($inboxAgents->viewableBy($user)));
    }
}
