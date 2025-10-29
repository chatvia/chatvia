<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Support\Ingestion;
use Illuminate\Http\Request;

class AgentKnowledgeFilesProcessController
{
    public function __invoke(Request $request, Agent $agent, Ingestion $ingestion)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $agent
            ->files()
            ->where('status', 'uploaded')
            ->each(function (AgentFile $agentFile) {
                $agentFile->ingest();
            });
    }
}
