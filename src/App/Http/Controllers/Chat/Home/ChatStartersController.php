<?php

namespace App\Http\Controllers\Chat\Home;

use Domain\Agents\Services\Agent;
use Illuminate\Http\Request;

final class ChatStartersController
{
    public function __invoke(Request $request)
    {
        $agent = Agent::resolveAgentFromSubdomain($request->agentSlug());

        return $agent->resolvedStarters();
    }
}
