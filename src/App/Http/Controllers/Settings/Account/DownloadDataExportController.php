<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Downloads a ready GDPR data export ZIP. Only the owner of the export may
 * download it (see DataExportPolicy).
 */
class DownloadDataExportController
{
    public function __invoke(DataExport $export): StreamedResponse
    {
        if (Gate::denies('download', $export)) {
            throw new AccessDeniedHttpException;
        }

        $filename = Str::slug((string) config('chatvia.brand.name')).'-export-'.$export->user_id.'-'.$export->created_at->format('Ymd-His').'.zip';

        return Storage::disk('local')->download($export->file_path, $filename);
    }
}
