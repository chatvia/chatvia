<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Policies;

use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Only the account that asked for a data export may download (while it is
 * ready) or delete it. Someone else's export answers 404, as if it did not
 * exist.
 */
class DataExportPolicy
{
    public function download(User $user, DataExport $export): Response
    {
        if ($export->user_id !== $user->id) {
            return Response::denyAsNotFound();
        }

        return $export->isReady() && ! $export->isExpired() ? Response::allow() : Response::deny();
    }

    public function delete(User $user, DataExport $export): Response
    {
        return $export->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
