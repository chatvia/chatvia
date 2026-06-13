<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;

final class ChatStarterController
{
    public function __invoke(Request $request)
    {
        $agent = AgentLocator::resolveAgentFromSubdomain($request->agentSlug());

        return $agent->resolvedStarters();
    }
}
