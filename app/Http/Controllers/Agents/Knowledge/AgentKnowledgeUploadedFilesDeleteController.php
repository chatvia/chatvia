<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Http\Request;

class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $agent->files()->where('status', 'uploaded')->delete();
    }
}
