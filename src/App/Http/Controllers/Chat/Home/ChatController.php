<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Domain\Llm\Services\DocumentLlm;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat page for one conversation. The messages are loaded by the
 * page itself (each with its uploaded files).
 */
final class ChatController
{
    public function __invoke(string $conversationId, #[HostedChatAgent] ?Agent $agent, DocumentLlm $documentLlm): Response
    {
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $agent?->loadMissing('team.owner');

        return Inertia::render('chat/Chat', [
            ...HostedChatPageProps::for($agent, $documentLlm),
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
