<?php

namespace Domain\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Source\File\Models\SourceFile;

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
