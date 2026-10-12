<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Account\Concerns;

use Carbon\CarbonImmutable;
use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Services\Period;
use Illuminate\Database\Query\Builder;

/**
 * Message counts, tokens and reply times for the account-analytics read
 * models, read from the per-agent hourly rollup instead of the raw event log.
 * The rollup holds one row per agent, hour, country and model, so a month is a
 * few thousand rows however many messages it had. Raw events stay for what a
 * sum cannot give (percentiles, per-chatter counts), and those reads are
 * bounded to the raw-event retention.
 *
 * A window is read by whole hours, from the hour its start falls in. Named
 * periods and date ranges start on the hour; only the "previous window" of a
 * trend can start mid-hour, and its first hour then counts in full.
 *
 * @phpstan-require-extends AccountAnalyticsQuery
 */
trait ReadsHourlyRollups
{
    /**
     * The scoped agents' hourly rollup rows for the window (the scope's period
     * unless another one is given).
     */
    protected function rollupHours(AnalyticsScopeData $scope, ?Period $period = null): Builder
    {
        $period ??= $scope->period;

        return $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $scope->workspace->id)
            ->whereBetween('bucket_hour', [$period->start->startOfHour(), $period->end])
            ->when($scope->agentId !== null, fn (Builder $query): Builder => $query->where('agent_id', $scope->agentId));
    }

    /**
     * The scope's period, starting no earlier than the oldest raw event the
     * daily prune keeps. Raw events before that are gone, so reading further
     * back only costs time.
     */
    protected function rawEventPeriod(AnalyticsScopeData $scope): Period
    {
        $period = $scope->period;
        $oldest = CarbonImmutable::now()->subDays(PruneAnalyticsEventsAction::DEFAULT_RETENTION_DAYS)->startOfDay();

        return $period->start->greaterThanOrEqualTo($oldest)
            ? $period
            : new Period($oldest, $period->end, $period->label, $period->named);
    }
}
