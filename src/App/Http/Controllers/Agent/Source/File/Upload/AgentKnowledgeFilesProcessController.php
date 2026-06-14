<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\ProcessAgentUploadedFilesAction;
use Illuminate\Http\Request;

class AgentKnowledgeFilesProcessController
{
    public function __invoke(Request $request, Agent $agent, ProcessAgentUploadedFilesAction $process): void
    {
        $process->execute($agent);
    }
}
