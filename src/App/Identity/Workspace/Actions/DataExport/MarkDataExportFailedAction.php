<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Jobs\PrepareDataExportJob;
use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Str;
use Throwable;

/**
 * Marks a data export failed with the error that stopped it, so the export
 * never hangs in "processing" on the Account page. Called from
 * {@see PrepareDataExportJob::failed()}.
 */
class MarkDataExportFailedAction
{
    public function execute(DataExport $export, Throwable $e): void
    {
        $export->update([
            'status' => DataExportStatus::Failed,
            'error_message' => Str::limit($e->getMessage(), 1000),
        ]);
    }
}
