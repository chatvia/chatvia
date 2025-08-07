<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Jobs\Ingestion\IngestionJob;
use App\Models\Agent;
use App\Models\AgentFile;
use App\Support\Ingestion;
use Illuminate\Http\Request;

class AgentKnowledgeFilesProcessController
{
    public function __invoke(Request $request, Agent $agent, Ingestion $ingestion)
    {
        $agent->files()->where('status', 'uploaded')->each(function (AgentFile $agentFile) {
            $agentFile->update(['status' => 'processing']);
            IngestionJob::dispatch($agentFile);
        });
    }
}
