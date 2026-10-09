<?php

namespace App\Identity\Workspace\Events;

use App\Identity\Workspace\Models\DataExport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A data export ZIP finished building and can be downloaded.
 */
class DataExportReady
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DataExport $export,
    ) {}
}
