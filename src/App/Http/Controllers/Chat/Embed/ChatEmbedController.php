<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Domain\Llm\Services\DocumentLlm;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One conversation of the hosted chat embedded in an iframe.
 */
class ChatEmbedController
{
    public function __invoke(string $conversationId, #[HostedChatAgent] ?Agent $agent, DocumentLlm $documentLlm): Response
    {
        return Inertia::render('chat/Chat', [
            ...HostedChatPageProps::for($agent, $documentLlm),
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'embedded' => true,
        ]);
    }
}
