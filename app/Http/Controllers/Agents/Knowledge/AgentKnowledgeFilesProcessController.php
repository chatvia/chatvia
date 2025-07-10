<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Support\Ingestion;
use Illuminate\Http\Request;

class AgentKnowledgeFilesProcessController
{
    public function __invoke(Request $request, Agent $agent, Ingestion $ingestion)
    {
        $agent->files()->where('status', 'uploaded')->each(function (AgentFile $agentFile) use ($ingestion) {
            $response = $ingestion->ingest($agentFile);
            $agentFile->update([
                'status' => 'processing',
                'external_ingestion_job_id' => $response['job_id'],
                'chunks' => $response['chunks'],
            ]);
        });
    }
}
