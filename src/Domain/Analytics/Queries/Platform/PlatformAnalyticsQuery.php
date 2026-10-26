<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Analytics\Services\Period;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared building blocks for the platform-analytics read models behind the
 * admin panel's dashboard: numbers across every workspace on the install, or
 * one workspace when the scope names it.
 *
 * Traffic reads the hourly rollup for windows up to 92 days and the daily one
 * beyond ({@see RollupGrain}); both survive deletion of the chat rows and stay
 * small. Every window starts on a full hour, so the hour buckets match it
 * exactly; a long window is read by whole days. Sign-ups, agents and leads read the main-DB tables. The
 * analytics tables may live in a separate database, so the two are never
 * joined in SQL.
 */
abstract class PlatformAnalyticsQuery
{
    /** The analytics DB connection (may be a separate Postgres database). */
    protected function analytics(): Connection
    {
        return app(AnalyticsDatabase::class)->connection();
    }

    /**
     * The per-agent hourly or daily rollup over the window (the scope's own
     * unless given), narrowed to the scoped workspace.
     */
    protected function rollups(PlatformScopeData $scope, ?Period $period = null): Builder
    {
        $period ??= $scope->period;
        $grain = RollupGrain::for($period);

        return $grain->within($this->analytics()->table($grain->table()), $period)
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId));
    }

    /**
     * Every workspace on the install, whatever the scope (`workspaces` rows).
     */
    protected function workspaceAccounts(): Builder
    {
        return DB::table('workspaces');
    }

    /** The scoped workspace's agents, or every agent on the install. */
    protected function agents(PlatformScopeData $scope): Builder
    {
        return DB::table('agents')
            ->when(! $scope->isWholePlatform(), fn (Builder $query): Builder => $query->where('workspace_id', $scope->workspaceId));
    }

    /** Percent-of-whole, null-safe. */
    protected function pct(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
