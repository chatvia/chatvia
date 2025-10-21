<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeTextDestroyController
{
    public function __invoke(Request $request, Agent $agent, string $id)
    {
        abort_if(
            boolean: $request->user()->cannot('delete', $agent),
            code: 404,
        );

        $agent->texts()->where('id', $id)->delete();
    }
}
