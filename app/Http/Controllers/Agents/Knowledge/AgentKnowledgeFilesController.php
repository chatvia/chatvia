<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Data\Agent\KnowledgeFileData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeFilesController
{
    public function __invoke(Request $request, Agent $agent)
    {
        return KnowledgeFileData::fromFiles($agent->files);
    }
}
