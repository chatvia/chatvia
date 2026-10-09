<?php

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Files\StartFileIngestionAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Starts ingesting every file of the agent that was uploaded but not yet sent
 * to ingestion (the "Train" button after an upload).
 */
class ProcessAgentUploadedFilesAction
{
    public function __construct(
        private readonly StartFileIngestionAction $startIngestion,
    ) {}

    public function execute(Agent $agent): void
    {
        $agent
            ->files()
            ->where('source_files.status', SourceStatus::Uploaded)
            ->each(fn (SourceFile $sourceFile) => $this->startIngestion->execute($sourceFile, $agent));
    }
}
