<?php

namespace App\Identity\Workspace\Data\DataExport;

use App\Identity\Workspace\Models\User;
use Spatie\LaravelData\Data;

class RequestDataExportData extends Data
{
    public function __construct(
        public User $user,
    ) {}
}
