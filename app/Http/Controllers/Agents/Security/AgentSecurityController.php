<?php

namespace App\Http\Controllers\Agents\Security;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Inertia\Inertia;

class AgentSecurityController extends Controller
{
    public function __invoke(Agent $agent)
    {
        return Inertia::render('agents/Security', [
            'agent' => $agent,
        ]);
    }
}
