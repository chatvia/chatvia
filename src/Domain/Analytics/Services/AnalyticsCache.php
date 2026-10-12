<?php

declare(strict_types=1);

namespace Domain\Analytics\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * A short-lived cache for the numbers the dashboard and analytics pages show.
 * They are sums over the analytics rollups, which grow with every message, and
 * a few minutes old is fine on a dashboard. A value is fresh for 5 minutes;
 * until it is 15 minutes old it is still served while one request refreshes
 * it after its response is sent ({@see Cache::flexible()}), so a busy page
 * never waits on the sums.
 *
 * Keys name the workspace, the agent when there is one, what is counted and
 * the date range (`lifetime` for all-time numbers), e.g.
 * `workspace:12:agent:34:totals:lifetime`.
 */
final class AnalyticsCache
{
    /** Seconds a value is fresh, and seconds until it expires. */
    private const array TTL = [300, 900];

    private const string PREFIX = 'analytics:';

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $compute
     * @return TValue
     */
    public function remember(string $key, Closure $compute): mixed
    {
        return Cache::flexible(self::PREFIX.$key, self::TTL, $compute);
    }

    /** The key of an agent's number: `workspace:{id}:agent:{id}:{what}:{range}`. */
    public static function agentKey(int $workspaceId, int $agentId, string $what, string $range = 'lifetime'): string
    {
        return template('workspace:{workspace}:agent:{agent}:{what}:{range}', [
            'workspace' => $workspaceId,
            'agent' => $agentId,
            'what' => $what,
            'range' => $range,
        ]);
    }

    /** The key of a workspace-wide number: `workspace:{id}:{what}:{range}`. */
    public static function workspaceKey(int $workspaceId, string $what, string $range = 'lifetime'): string
    {
        return template('workspace:{workspace}:{what}:{range}', [
            'workspace' => $workspaceId,
            'what' => $what,
            'range' => $range,
        ]);
    }
}
