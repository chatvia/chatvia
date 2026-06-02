<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Agent\Services\Agent;
use Illuminate\Http\Request;

final class ChatStarterController
{
    public function __invoke(Request $request)
    {
        $agent = Agent::resolveAgentFromSubdomain($request->agentSlug());

        return $agent->resolvedStarters();
    }
}
