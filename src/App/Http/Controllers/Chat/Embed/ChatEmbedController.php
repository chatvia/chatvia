<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatPageProps;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One conversation of the hosted chat embedded in an iframe.
 */
class ChatEmbedController
{
    public function __invoke(string $conversationId): Response
    {
        $agent = current_agent();

        return Inertia::render('chat/Chat', [
            ...HostedChatPageProps::for($agent),
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
