<?php

namespace Domain\Agent\Actions\Sources;

use Domain\Agent\Models\Agent;
use Domain\Source\File\Models\SourceFile;

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
