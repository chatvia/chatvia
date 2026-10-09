<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One conversation of the hosted chat embedded in an iframe.
 */
class ChatEmbedController
{
    public function __invoke(string $conversationId, #[HostedChatAgent] ?Agent $agent): Response
    {
        return Inertia::render('chat/Chat', [
            ...HostedChatPageProps::for($agent),
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
