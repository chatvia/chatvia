<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\MessageStatsData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;
use Domain\Analytics\Services\Period;

/**
 * Message volume — visitor messages vs AI replies — with the trend against
 * the previous equally long window and a per-day series, from the hourly or
 * daily rollup.
 */
class MessageStatsQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    public function get(AnalyticsScopeData $scope): MessageStatsData
    {
        [$incoming, $outgoing] = $this->totals($scope, $scope->period);
        [$previousIncoming, $previousOutgoing] = $this->totals($scope, $scope->period->previousWindow());

        $total = $incoming + $outgoing;
        $previousTotal = $previousIncoming + $previousOutgoing;

        return new MessageStatsData(
            incoming: $incoming,
            outgoing: $outgoing,
            previousTotal: $previousTotal,
            changePct: $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100, 1) : null,
            avgPerConversation: $this->roundOrNull($this->conversationMetrics($scope)->avg('message_count'), 1),
            daily: $this->daily($scope),
        );
    }

    /**
     * @return array{0: int, 1: int} Visitor messages and AI replies.
     */
    private function totals(AnalyticsScopeData $scope, Period $period): array
    {
        $row = $this->rollup($scope, $period)
            ->selectRaw('COALESCE(SUM(user_messages),0) incoming, COALESCE(SUM(assistant_messages),0) outgoing')
            ->first();

        return [(int) $row->incoming, (int) $row->outgoing];
    }

    /**
     * @return list<DailyCountData>
     */
    private function daily(AnalyticsScopeData $scope): array
    {
        return $this->rollup($scope)
            ->selectRaw($this->grain($scope)->day().' date, SUM(user_messages + assistant_messages) messages')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(static fn (object $row): DailyCountData => new DailyCountData((string) $row->date, (int) $row->messages))
            ->all();
    }
}
