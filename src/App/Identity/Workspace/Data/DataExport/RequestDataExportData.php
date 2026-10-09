<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\DataExport;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

/**
 * Inputs for requesting a data export.
 */
class RequestDataExportData extends Data
{
    public function __construct(
        public User $user,
    ) {}
}
