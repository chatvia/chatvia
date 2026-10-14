<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat page for one conversation. The messages are loaded by the
 * page itself (each with its uploaded files).
 */
final class ChatController
{
    public function __invoke(string $conversationId, #[HostedChatAgent] ?Agent $agent, HostedChatPageProps $props): Response
    {
        $agent?->loadSettingsGroups(['appearance', 'embed']);

        return Inertia::render('chat/Chat', [
            ...$props->for($agent),
            'agent_id' => $agent?->id,
            'conversation_id' => $conversationId,
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
