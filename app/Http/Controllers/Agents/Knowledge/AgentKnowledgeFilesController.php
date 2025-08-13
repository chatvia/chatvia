<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Data\Agent\KnowledgeFileData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeFilesController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        return KnowledgeFileData::fromFiles($agent->files);
    }
}
