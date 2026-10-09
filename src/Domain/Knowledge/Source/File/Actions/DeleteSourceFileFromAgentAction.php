<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Files\CancelFileIngestionAction;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a file source. A file that is still being ingested has its chunk
 * batch cancelled first, so no chunk is created for a deleted file. The
 * observer and the Chunkable trait remove the stored file and its chunks.
 */
class DeleteSourceFileFromAgentAction
{
    public function __construct(
        private readonly CancelFileIngestionAction $cancelIngestion,
    ) {}

    public function execute(Agent $agent, SourceFile $file): void
    {
        if ($file->status === SourceStatus::Processing) {
            $this->cancelIngestion->execute($file);
        }

        DB::transaction(function () use ($agent, $file): void {
            $file->agents()->detach($agent->id);
            $file->delete();
        });
    }
}
