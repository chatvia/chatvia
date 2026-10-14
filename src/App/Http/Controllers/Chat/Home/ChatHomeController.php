<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat's start page.
 */
class ChatHomeController
{
    public function __invoke(#[HostedChatAgent] ?Agent $agent, HostedChatPageProps $props): Response
    {
        $agent?->loadSettingsGroups(['appearance', 'embed']);

        return Inertia::render('chat/Home', [
            ...$props->for($agent),
            'agent_id' => $agent?->id,
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
