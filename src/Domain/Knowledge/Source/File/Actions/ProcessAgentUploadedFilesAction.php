<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

class ProcessAgentUploadedFilesAction
{
    public function execute(Agent $agent): void
    {
        $agent
            ->files()
            ->where('source_files.status', 'uploaded')
            ->each(function (SourceFile $agentFile) use ($agent): void {
                $agentFile->ingest($agent->id);
            });
    }
}
