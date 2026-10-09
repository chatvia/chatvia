<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Domain\Llm\Services\DocumentLlm;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start page of the hosted chat embedded in an iframe.
 */
class ChatEmbedHomeController
{
    public function __invoke(#[HostedChatAgent] ?Agent $agent, DocumentLlm $documentLlm): Response
    {
        return Inertia::render('chat/Home', [
            ...HostedChatPageProps::for($agent, $documentLlm),
            'chatRoute' => 'chat.embed.show',
            'newChatRoute' => 'chat.embed.home',
        ]);
    }
}
