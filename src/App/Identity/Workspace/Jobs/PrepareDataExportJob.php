<?php

namespace App\Identity\Workspace\Jobs;

use App\Identity\Workspace\Actions\DataExport\BuildDataExportArchiveAction;
use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class PrepareDataExportJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public int $dataExportId,
    ) {
        $this->onQueue('exports');
    }

    public function handle(BuildDataExportArchiveAction $action): void
    {
        $export = DataExport::find($this->dataExportId);

        if ($export === null) {
            return;
        }

        $action->execute($export);
    }

    public function failed(Throwable $e): void
    {
        $export = DataExport::find($this->dataExportId);

        if ($export === null) {
            return;
        }

        $export->update([
            'status' => DataExportStatus::Failed,
            'error_message' => Str::limit($e->getMessage(), 1000),
        ]);
    }

    public function uniqueId(): string
    {
        return 'data-export:'.$this->dataExportId;
    }
}
