<?php

namespace App\Http\Controllers\Chat\Embed;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatPageProps;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start page of the hosted chat embedded in an iframe.
 */
class ChatEmbedHomeController
{
    public function __invoke(#[HostedChatAgent] ?Agent $agent, HostedChatPageProps $props): Response
    {
        return Inertia::render('chat/pages/Home', [
            ...$props->for($agent),
            'embedded' => true,
        ]);
    }
}
