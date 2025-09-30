<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Http\Controllers\Controller;
use App\Models\Agent;

class AgentKnowledgeTextShowController extends Controller
{
    public function __invoke(Agent $agent, int $id)
    {
        return $agent->texts()->find($id);
    }
}
