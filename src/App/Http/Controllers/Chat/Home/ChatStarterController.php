<?php

namespace App\Http\Controllers\Chat\Home;

use App\Http\Chat\HostedChatAgent;
use Domain\Agent\Models\Agent;

/**
 * The conversation starters the hosted chat suggests.
 */
final class ChatStarterController
{
    /**
     * @return array<int, array{text: string, icon: string|null}>
     */
    public function __invoke(#[HostedChatAgent] Agent $agent): array
    {
        return $agent->resolvedStarters();
    }
}
