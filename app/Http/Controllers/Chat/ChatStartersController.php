<?php

namespace App\Http\Controllers\Chat;

use App\Support\Agent;
use Illuminate\Http\Request;

final class ChatStartersController
{
    public function __invoke(Request $request)
    {
        $agent = Agent::resolveAgentFromSubdomain($request->subdomain());

        return $agent->starters;
    }
}
