<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Queries;

use App\Identity\Workspace\Enums\DataExportStatus;
use App\Identity\Workspace\Models\DataExport;
use App\Identity\Workspace\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The user's export that still matters on the GDPR export page: one being
 * prepared, one that failed, or a ready ZIP that has not expired yet.
 */
class ActiveDataExportQuery
{
    public function forUser(User $user): ?DataExport
    {
        return DataExport::query()
            ->where('user_id', $user->id)
            ->where(function (Builder $query): void {
                $query->whereIn('status', [
                    DataExportStatus::Pending,
                    DataExportStatus::Processing,
                    DataExportStatus::Failed,
                ])->orWhere(function (Builder $query): void {
                    $query->where('status', DataExportStatus::Ready)
                        ->where('expires_at', '>', now());
                });
            })
            ->latest('id')
            ->first();
    }
}
