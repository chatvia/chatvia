<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Http\Request;

class AgentKnowledgeFileDeleteController
{
    public function __invoke(Request $request, Agent $agent, AgentFile $file)
    {
        $file->delete();
    }
}
