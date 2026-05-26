<?php

namespace Domain\Agents\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;

class UnlinkSourceFileFromAgentAction
{
    /**
     * Returns true if unlinked, false if the file is linked to multiple agents
     * (in which case caller should refuse).
     */
    public function execute(Agent $agent, SourceFile $file): bool
    {
        if ($file->agents()->count() > 1) {
            return false;
        }

        $agent->files()->detach($file->id);

        return true;
    }
}
