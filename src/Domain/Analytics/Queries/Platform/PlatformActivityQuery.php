<?php

declare(strict_types=1);

namespace Domain\Analytics\Queries\Platform;

use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Enums\TimeBucket;
use Domain\Analytics\Services\Period;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The admin dashboard's activity over time: messages, conversations,
 * workspace sign-ups and new agents per hour, day or month (by the window's
 * length), oldest first, with every bucket present.
 *
 * "All time" starts at the first recorded activity rather than the period's
 * nominal start, so the chart does not open on years of empty months.
 */
class PlatformActivityQuery extends PlatformAnalyticsQuery
{
    /**
     * @return list<ActivityBucketData>
     */
    public function get(PlatformScopeData $scope): array
    {
        $window = new Period($this->seriesStart($scope), $scope->period->end, $scope->period->label);
        $bucket = TimeBucket::spanning($window->start, $window->end);

        $traffic = $this->rollups($scope, $window)
            ->selectRaw('date_trunc(?, '.RollupGrain::for($window)->moment().') bucket', [$bucket->value])
            ->selectRaw('SUM(user_messages) visitor_messages, SUM(assistant_messages) ai_replies, SUM(conversations_started) conversations')
            ->groupBy('bucket')
            ->get()
            ->keyBy(fn (object $row): string => $this->key($row->bucket));

        $workspaces = $scope->isWholePlatform()
            ? $this->createdPerBucket($this->workspaceAccounts(), $bucket, $window)
            : collect();
        $agents = $this->createdPerBucket($this->agents($scope), $bucket, $window);

        $series = [];

        for ($at = $bucket->floor($window->start); $at <= $window->end; $at = $bucket->next($at)) {
            $key = $at->format('Y-m-d H:i:s');
            $row = $traffic->get($key);

            $series[] = new ActivityBucketData(
                start: $key,
                label: $bucket->label($at),
                visitorMessages: (int) ($row->visitor_messages ?? 0),
                aiReplies: (int) ($row->ai_replies ?? 0),
                conversations: (int) ($row->conversations ?? 0),
                newWorkspaces: (int) $workspaces->get($key, 0),
                newAgents: (int) $agents->get($key, 0),
            );
        }

        return $series;
    }

    /**
     * Rows created per bucket, keyed by the bucket's start.
     *
     * @return Collection<string, int>
     */
    private function createdPerBucket(Builder $query, TimeBucket $bucket, Period $window): Collection
    {
        return $query
            ->whereBetween('created_at', [$window->start, $window->end])
            ->selectRaw('date_trunc(?, created_at) bucket, COUNT(*) total', [$bucket->value])
            ->groupBy('bucket')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$this->key($row->bucket) => (int) $row->total]);
    }

    private function seriesStart(PlatformScopeData $scope): CarbonImmutable
    {
        if ($scope->period->named !== AnalyticsPeriod::AllTime) {
            return $scope->period->start;
        }

        $earliest = collect([
            $this->rollups($scope)->min($this->analytics()->raw(RollupGrain::for($scope->period)->moment())),
            $scope->isWholePlatform() ? $this->workspaceAccounts()->min('created_at') : null,
            $this->agents($scope)->min('created_at'),
        ])
            ->filter()
            ->map(fn (string $at): CarbonImmutable => CarbonImmutable::parse($at))
            ->min();

        return $earliest instanceof CarbonImmutable
            ? $earliest->max($scope->period->start)
            : $scope->period->end->startOfDay();
    }

    private function key(string $bucket): string
    {
        return CarbonImmutable::parse($bucket)->format('Y-m-d H:i:s');
    }
}
