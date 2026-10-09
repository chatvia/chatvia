<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Policies;

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;

/**
 * Only the account that asked for a data export may download (while it is
 * ready) or delete it.
 */
class DataExportPolicy
{
    public function download(User $user, DataExport $export): bool
    {
        return $export->user_id === $user->id
            && $export->isReady()
            && ! $export->isExpired();
    }

    public function delete(User $user, DataExport $export): bool
    {
        return $export->user_id === $user->id;
    }
}
