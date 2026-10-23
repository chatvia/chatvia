<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbox;

use App\Http\Resources\Inbox\InboxConversationData;
use App\Http\Resources\Inbox\InboxConversationsResourceData;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Queries\InboxConversationsQuery;
use Domain\Conversation\Services\Inbox\InboxAgents;
use Illuminate\Http\Request;
use Spatie\LaravelData\DataCollection;

/**
 * The Inbox list as JSON: online, waiting and held chats across every agent
 * the signed-in person may view. Someone with no such agent gets an empty
 * list. The page polls this while it is open.
 */
class InboxConversationController
{
    public function index(Request $request, InboxAgents $inboxAgents, InboxConversationsQuery $inbox): InboxConversationsResourceData
    {
        /** @var User $user */
        $user = $request->user();
        $agents = $inboxAgents->viewableBy($user);
        $mayJoin = $agents->mapWithKeys(fn (Agent $agent): array => [$agent->id => $inboxAgents->canReplyOn($user, $agent)]);

        $rows = $inbox->get($agents, $user)->map(
            fn (Conversation $conversation): InboxConversationData => InboxConversationData::fromConversation(
                $conversation,
                $user,
                (bool) $mayJoin->get($conversation->agent_id, false),
            ),
        );

        return new InboxConversationsResourceData(
            conversations: new DataCollection(InboxConversationData::class, $rows->all()),
            waiting_count: $inbox->waitingCount($agents),
        );
    }
}
