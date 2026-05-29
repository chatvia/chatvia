<?php

namespace App\Http\Controllers\Chat;

use Domain\Agents\Services\Agent;
use Illuminate\Http\Request;

final class ChatStartersController
{
    public function __invoke(Request $request)
    {
        $agent = Agent::resolveAgentFromSubdomain($request->agentSlug());

        return $agent->starters;
    }
}
