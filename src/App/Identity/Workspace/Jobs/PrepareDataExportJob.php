<?php

namespace App\Identity\Workspace\Jobs;

use App\Identity\Workspace\Actions\DataExport\BuildDataExportArchiveAction;
use App\Identity\Workspace\Actions\DataExport\MarkDataExportFailedAction;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Attributes\UniqueFor;
use Throwable;

/**
 * Builds a data export ZIP off the request, on the `exports` queue.
 */
#[Queue('exports')]
// One try: failed() marks the export failed, so the user sees it at once.
#[Tries(1)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
// Longer than a run, so a worker that dies mid-run cannot hold the lock for good.
#[UniqueFor(3600)]
class PrepareDataExportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $dataExportId,
    ) {}

    public function handle(BuildDataExportArchiveAction $action): void
    {
        $export = DataExport::query()->find($this->dataExportId);

        if ($export === null) {
            return;
        }

        $action->execute($export);
    }

    public function failed(Throwable $e): void
    {
        $export = DataExport::query()->find($this->dataExportId);

        if ($export === null) {
            return;
        }

        app(MarkDataExportFailedAction::class)->execute($export, $e);
    }

    public function uniqueId(): string
    {
        return "data-export:{$this->dataExportId}";
    }
}
