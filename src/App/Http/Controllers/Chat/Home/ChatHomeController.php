<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatPageProps;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The hosted chat's start page.
 */
class ChatHomeController
{
    public function __invoke(): Response
    {
        $agent = current_agent();
        $agent?->loadSettingsGroups(['appearance', 'embed']);
        $agent?->loadMissing('team.owner');

        return Inertia::render('chat/Home', [
            ...HostedChatPageProps::for($agent),
            'agent_id' => $agent?->id,
            'chatviaBranding' => $agent?->showsChatviaBranding() ?? true,
        ]);
    }
}
