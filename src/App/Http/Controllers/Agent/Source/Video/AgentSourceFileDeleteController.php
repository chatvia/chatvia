<?php

namespace App\Http\Controllers\Agent\Source\Video;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Deletes one of the agent's files (any kind). The `agent.sources.delete`
 * permission is checked by the route middleware.
 */
class AgentSourceFileDeleteController
{
    public function __invoke(Agent $agent, SourceFile $file, DeleteSourceFileFromAgentAction $delete): void
    {
        // {file} binds globally by id: only a file this agent uses may be
        // deleted through it.
        abort_unless($agent->files()->whereKey($file->id)->exists(), 404);

        $delete->execute($agent, $file);
    }
}
