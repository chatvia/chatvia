<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $agent->files()->where('status', 'uploaded')->delete();
    }
}
