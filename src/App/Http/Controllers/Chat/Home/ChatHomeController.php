<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Domain\Llm\Services\DocumentLlm;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat's start page.
 */
class ChatHomeController
{
    public function __invoke(#[HostedChatAgent] ?Agent $agent, DocumentLlm $documentLlm): Response
    {
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $agent?->loadMissing('team.owner');

        return Inertia::render('chat/Home', [
            ...HostedChatPageProps::for($agent, $documentLlm),
            'agent_id' => $agent?->id,
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
