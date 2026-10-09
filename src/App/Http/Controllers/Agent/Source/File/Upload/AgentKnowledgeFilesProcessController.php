<?php

namespace App\Http\Controllers\Agent\Source\File\Upload;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\ProcessAgentUploadedFilesAction;

/**
 * Starts training every uploaded file of the agent. The
 * `agent.sources.train.files` permission is checked by the route middleware.
 */
class AgentKnowledgeFilesProcessController
{
    public function __invoke(Agent $agent, ProcessAgentUploadedFilesAction $process): void
    {
        $process->execute($agent);
    }
}
