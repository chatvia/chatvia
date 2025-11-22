<?php

namespace App\Http\Controllers\Agents\Sources;

use App\Models\Agent;
use App\Models\SourceFile;
use App\Services\Ingestion\Ingestion;
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
            ->where('source_files.status', 'uploaded')
            ->each(function (SourceFile $agentFile) use ($agent) {
                $agentFile->ingest($agent->id);
            });
    }
}
