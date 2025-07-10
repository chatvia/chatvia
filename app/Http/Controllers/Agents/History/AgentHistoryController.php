<?php

namespace App\Http\Controllers\Agents\History;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Inertia\Inertia;

class AgentHistoryController extends Controller
{
    public function __invoke(Agent $agent)
    {
        return Inertia::render('agents/History', [
            'agent' => $agent,
            'settings' => $agent->settings('message_history'),
        ]);
    }
}
