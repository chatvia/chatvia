<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads a ready GDPR data export ZIP. Only the owner of the export may
 * download it (see DataExportPolicy).
 */
class DownloadDataExportController
{
    public function __invoke(DataExport $export): StreamedResponse
    {
        // Someone else's export is a 404; a not-ready one is a 403 (DataExportPolicy).
        Gate::authorize('download', $export);

        $filename = "chatvia-export-{$export->user_id}-{$export->created_at->format('Ymd-His')}.zip";

        return Storage::disk('local')->download($export->file_path, $filename);
    }
}
