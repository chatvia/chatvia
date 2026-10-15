<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Actions\Account\DeactivateAccountAction;
use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Actions\Account\ReactivateAccountAction;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a workspace is switched on, so its agents may answer. A workspace
 * is switched off through its owner's account ({@see DeactivateAccountAction},
 * also used when its deletion starts: {@see DeleteAccountAction}); while it is
 * off, every surface and channel refuses its agents' turns.
 *
 * The answer is cached per workspace, because the hosted chat and widget
 * check it on every request. Deactivating or reactivating the owner drops it
 * ({@see ReactivateAccountAction}); the time limit repairs any other change.
 */
class WorkspaceActivation
{
    private const string CACHE_KEY = 'workspace-active:{workspace}';

    private const int CACHE_TTL_HOURS = 6;

    public function isActive(int $workspaceId): bool
    {
        return (bool) Cache::remember(
            $this->cacheKey($workspaceId),
            now()->addHours(self::CACHE_TTL_HOURS),
            fn (): bool => Workspace::query()
                ->whereKey($workspaceId)
                ->whereHas('owner', fn (Builder $owner): Builder => $owner->whereNotNull('users.deactivated_at'))
                ->doesntExist(),
        );
    }

    public function forget(int $workspaceId): void
    {
        Cache::forget($this->cacheKey($workspaceId));
    }

    private function cacheKey(int $workspaceId): string
    {
        return template(self::CACHE_KEY, ['workspace' => $workspaceId]);
    }
}
