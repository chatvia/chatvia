<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Services\Ingestion;
use Domain\Knowledge\Source\File\Models\SourceFile;

class ProcessAgentUploadedFilesAction
{
    public function __construct(private readonly Ingestion $ingestion) {}

    public function execute(Agent $agent): void
    {
        $agent
            ->files()
            ->where('source_files.status', 'uploaded')
            ->each(function (SourceFile $agentFile) use ($agent): void {
                $this->ingestion->ingest($agentFile, $agent);
            });
    }
}
