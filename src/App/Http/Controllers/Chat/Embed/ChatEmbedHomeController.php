<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatPageProps;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start page of the hosted chat embedded in an iframe.
 */
class ChatEmbedHomeController
{
    public function __invoke(): Response
    {
        return Inertia::render('chat/Home', [
            ...HostedChatPageProps::for(current_agent()),
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
