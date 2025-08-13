<?php

namespace App\Http\Controllers\Agents\Knowledge;

use App\Models\Agent;
use App\Models\AgentFile;
use Illuminate\Http\Request;

class AgentKnowledgeFileDeleteController
{
    public function __invoke(Request $request, Agent $agent, AgentFile $file)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        if ($file->status === 'processing') {
            $file->cancelIngestionJob();
        }

        $file->delete();
    }
}
