<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Actions\Dashboard\GetDashboardStatsAction;
use Domain\Analytics\Data\DateRangeData;
use Domain\Analytics\Queries\Agent\AgentDailyCountsQuery;
use Domain\Analytics\Queries\Agent\AgentMessageTotalsQuery;
use Domain\Analytics\Queries\Agent\AgentTotalsQuery;
use Illuminate\Support\Carbon;

/**
 * The dashboard and analytics page numbers come from a short-lived cache: a
 * repeat visit within five minutes reuses them, and another agent, another
 * set of agents or another date range is computed on its own.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-06-15 12:00:00', 'UTC'));
});

it('reuses the dashboard numbers within five minutes and recounts on a new day', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    analyticsRollup($agent, '2026-06-15 10:00:00+00', ['conversations_started' => 2, 'assistant_messages' => 3]);
    $first = app(GetDashboardStatsAction::class)->execute($owner, null);

    analyticsRollup($agent, '2026-06-15 11:00:00+00', ['conversations_started' => 5, 'assistant_messages' => 7]);
    $this->travel(4)->minutes();
    $second = app(GetDashboardStatsAction::class)->execute($owner, null);

    $this->travelTo(Carbon::parse('2026-06-16 09:00:00', 'UTC'));
    $nextDay = app(GetDashboardStatsAction::class)->execute($owner, null);

    expect($first->conversations)->toBe(2)
        ->and($second->conversations)->toBe(2)
        ->and($second->messages->total)->toBe(3)
        ->and($nextDay->conversations)->toBe(7)
        ->and($nextDay->messages->total)->toBe(10);
});

it('keeps the agent totals of each agent apart', function (): void {
    $owner = User::factory()->create();
    $one = createAgent($owner);
    $two = createAgent($owner);

    analyticsRollup($one, '2026-06-15 10:00:00+00', ['assistant_messages' => 4]);
    analyticsRollup($two, '2026-06-15 10:00:00+00', ['assistant_messages' => 9]);

    $totals = app(AgentTotalsQuery::class);
    $first = $totals->get($one)->assistantMessages;

    analyticsRollup($one, '2026-06-15 11:00:00+00', ['assistant_messages' => 1]);

    expect($first)->toBe(4)
        ->and($totals->get($one)->assistantMessages)->toBe(4)
        ->and($totals->get($two)->assistantMessages)->toBe(9);
});

it('caches the daily series per date range', function (): void {
    $agent = createAgent(User::factory()->create());

    analyticsRollup($agent, '2026-06-10 10:00:00+00', ['assistant_messages' => 2]);
    analyticsRollup($agent, '2026-06-12 10:00:00+00', ['assistant_messages' => 5]);

    $daily = app(AgentDailyCountsQuery::class);
    $narrow = DateRangeData::fromDates('2026-06-10', '2026-06-10');
    $wide = DateRangeData::fromDates('2026-06-10', '2026-06-12');

    expect(array_sum(array_column($daily->replies($agent, $narrow->start, $narrow->end), 'count')))->toBe(2)
        ->and(array_sum(array_column($daily->replies($agent, $wide->start, $wide->end), 'count')))->toBe(7);
});

it('caches the agents list totals per set of agents', function (): void {
    $owner = User::factory()->create();
    $one = createAgent($owner);
    $two = createAgent($owner);

    analyticsRollup($one, '2026-06-15 10:00:00+00', ['user_messages' => 1, 'assistant_messages' => 1]);
    analyticsRollup($two, '2026-06-15 10:00:00+00', ['user_messages' => 3, 'assistant_messages' => 3]);

    $totals = app(AgentMessageTotalsQuery::class);
    $workspaceId = $one->workspace_id;

    expect($totals->get($workspaceId, [$one->id]))->toBe([$one->id => 2]);

    analyticsRollup($one, '2026-06-15 11:00:00+00', ['user_messages' => 5]);

    // Same set (in any order) → cached; a new set → counted now.
    expect($totals->get($workspaceId, [$one->id]))->toBe([$one->id => 2])
        ->and($totals->get($workspaceId, [$two->id, $one->id]))->toEqual([$one->id => 7, $two->id => 6]);
});
