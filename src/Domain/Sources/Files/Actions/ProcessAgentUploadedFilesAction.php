<?php

namespace Domain\Sources\Files\Actions;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;

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
