<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Account\Concerns;

use Carbon\CarbonImmutable;
use Domain\Analytics\Actions\Retention\PruneAnalyticsEventsAction;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Services\Period;
use Illuminate\Database\Query\Builder;

/**
 * Message counts, tokens and reply times for the account-analytics read
 * models, read from the per-agent rollups instead of the raw event log: the
 * hourly one for ranges up to 92 days, the daily one beyond
 * ({@see RollupGrain}). Either way a range is a row per agent and hour or day,
 * however many messages it had. Raw events stay for what a sum cannot give
 * (per-chatter counts), and those reads are bounded to the raw-event
 * retention.
 *
 * An hourly window is read by whole hours, from the hour its start falls in;
 * a daily one by whole days. Named periods and date ranges start on the hour;
 * only the "previous window" of a trend can start mid-hour, and its first
 * hour (or day) then counts in full.
 *
 * @phpstan-require-extends AccountAnalyticsQuery
 */
trait ReadsRollups
{
    /** The scope's rollup rows in the period (the scope's own by default). */
    protected function rollup(AnalyticsScopeData $scope, ?Period $period = null): Builder
    {
        $period ??= $scope->period;
        $grain = RollupGrain::for($period);

        return $grain->within($this->analytics()->table($grain->table()), $period)
            ->where('workspace_id', $scope->workspace->id)
            ->when($scope->agentId !== null, fn (Builder $query): Builder => $query->where('agent_id', $scope->agentId));
    }

    /** The rollup a period is summed from, for its day expression. */
    protected function grain(AnalyticsScopeData $scope, ?Period $period = null): RollupGrain
    {
        return RollupGrain::for($period ?? $scope->period);
    }

    /**
     * The scope's period cut to the raw events still kept: raw events older
     * than the retention are pruned, so a longer period starts at the oldest.
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
