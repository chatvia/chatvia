<?php

namespace Domain\Sources\Files\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;

class DeleteSourceFileFromAgentAction
{
    public function execute(Agent $agent, SourceFile $file): void
    {
        if ($file->status === 'ingesting') {
            $file->cancelIngestionJob();
        }

        $file->agents()->detach($agent->id);
        $file->delete();
    }
}
