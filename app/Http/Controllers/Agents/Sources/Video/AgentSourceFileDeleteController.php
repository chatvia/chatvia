<?php

namespace App\Http\Controllers\Agents\Sources\Video;

use App\Models\Agent;
use App\Models\SourceFile;
use Illuminate\Http\Request;

class AgentSourceFileDeleteController
{
    public function __invoke(Request $request, Agent $agent, SourceFile $file)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.delete'),
            code: 403,
        );

        if ($file->status === 'ingesting') {
            $file->cancelIngestionJob();
        }

        $file->agents()->detach($agent->id);
        $file->delete();
    }
}
