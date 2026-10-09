<?php

namespace App\Identity\Workspace\Actions\DataExport;

use App\Identity\Workspace\Models\DataExport;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes a data export the owner no longer wants: the ZIP on the private
 * local disk, then its row. The file goes first so a failed delete never
 * leaves a ZIP with no row pointing at it.
 */
class DeleteDataExportAction
{
    public function execute(DataExport $export): void
    {
        if ($export->file_path) {
            Storage::disk('local')->delete($export->file_path);
        }

        $export->delete();
    }
}
