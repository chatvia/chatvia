<?php

namespace App\Http\Controllers\Chat\Auth;

use Illuminate\Http\Request;

class ChatterAgentController
{
    public function __invoke(Request $request)
    {
        return $request
            ->user()
            ->accessibleAgents()
            ->where('agents.type', 'knowledge_base')
            ->select(['agents.id', 'agents.external_id', 'agents.name', 'agents.sub_domain'])
            ->get();
    }
}
