<?php

namespace App\Http\Controllers\Agent\Source\File\Manage;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteUploadedSourceFilesAction;

/**
 * Discards the agent's uploaded files that were never sent to training. The
 * `agent.sources.delete` permission is checked by the route middleware.
 */
class AgentKnowledgeUploadedFilesDeleteController
{
    public function __invoke(Agent $agent, DeleteUploadedSourceFilesAction $deleteUploaded): void
    {
        $deleteUploaded->execute($agent);
    }
}
