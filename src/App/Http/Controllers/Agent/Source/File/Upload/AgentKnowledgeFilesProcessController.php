<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\ProcessAgentUploadedFilesAction;
use Illuminate\Http\Request;

class AgentKnowledgeFilesProcessController
{
    public function __invoke(Request $request, Agent $agent, ProcessAgentUploadedFilesAction $process): void
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.files'), 403);

        $process->execute($agent);
    }
}
