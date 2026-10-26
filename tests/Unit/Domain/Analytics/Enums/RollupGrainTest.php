<?php

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Queries\Account\Activity\MessageStatsQuery;
use Domain\Analytics\Queries\Account\Usage\TokenUsageQuery;
use Domain\Analytics\Services\AnalyticsScopeResolver;
use Domain\Analytics\Services\Period;

/**
 * RollupGrain: a range of up to 92 days is summed from the hourly rollup, a
 * longer one from the daily rollup, and both give the same totals and the
 * same days for the same messages.
 */
function rgPeriod(string $start, string $end): Period
{
    return new Period(CarbonImmutable::parse($start)->startOfDay(), CarbonImmutable::parse($end)->endOfDay(), 'range');
}

it('sums up to 92 days from the hourly rollup and longer ranges from the daily one', function (): void {
    expect(RollupGrain::for(rgPeriod('2026-01-01', '2026-04-02')))->toBe(RollupGrain::Hourly)
        ->and(RollupGrain::for(rgPeriod('2026-01-01', '2026-04-03')))->toBe(RollupGrain::Daily)
        ->and(RollupGrain::Hourly->table())->toBe('analytics_agent_hourly_rollups')
        ->and(RollupGrain::Daily->table())->toBe('analytics_agent_daily');
});

it('gives the same totals and days on either side of the switch', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-20 12:00:00'));
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    analyticsRollup($agent, '2026-06-10 09:00:00', ['user_messages' => 3, 'assistant_messages' => 3, 'total_tokens' => 300]);
    analyticsRollup($agent, '2026-06-10 15:00:00', ['user_messages' => 1, 'assistant_messages' => 2, 'total_tokens' => 200]);
    analyticsRollup($agent, '2026-06-12 10:00:00', ['user_messages' => 4, 'assistant_messages' => 4, 'total_tokens' => 50]);

    $scope = fn (string $start): mixed => app(AnalyticsScopeResolver::class)->resolve($owner->workspace(), new AnalyticsFilterData(startDate: $start, endDate: '2026-06-20'));
    // 92 days and 93 days back from 2026-06-20.
    $hourly = $scope('2026-03-21');
    $daily = $scope('2026-03-20');

    expect(RollupGrain::for($hourly->period))->toBe(RollupGrain::Hourly)
        ->and(RollupGrain::for($daily->period))->toBe(RollupGrain::Daily);

    $messages = app(MessageStatsQuery::class);
    $tokens = app(TokenUsageQuery::class);

    expect($messages->get($daily))->toEqual($messages->get($hourly))
        ->and($tokens->get($daily))->toEqual($tokens->get($hourly))
        ->and($messages->get($daily)->incoming)->toBe(8)
        ->and(collect($messages->get($daily)->daily)->mapWithKeys(fn ($day): array => [$day->date => $day->count])->all())->toBe(['2026-06-10' => 9, '2026-06-12' => 8]);
});
