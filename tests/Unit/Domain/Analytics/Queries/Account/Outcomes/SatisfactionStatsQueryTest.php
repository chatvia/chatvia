<?php

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Outcomes\SatisfactionStatsQuery;
use Domain\Analytics\Services\Period;

/**
 * The AI reply figures of the satisfaction stats (replies, unanswered,
 * average confidence) come from the hourly rollup, for the scope's window and
 * agent, with no chat log behind them.
 */
function sssScope(Agent $agent, ?int $agentId = null): AnalyticsScopeData
{
    $period = new Period(CarbonImmutable::parse('2026-06-01 00:00:00'), CarbonImmutable::parse('2026-06-15 12:00:00'), 'this month');

    return new AnalyticsScopeData($agent->workspace, $period, $agentId, $agentId !== null ? [$agentId] : [$agent->id]);
}

it('reads the replies, the unanswered ones and the average confidence from the rollup', function (): void {
    $workspace = User::factory()->create()->workspace();
    $alpha = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $beta = Agent::factory()->create(['workspace_id' => $workspace->id]);

    analyticsRollup($alpha, '2026-06-10 09:00:00', ['assistant_messages' => 4, 'unanswered_replies' => 1, 'confidence_sum' => 2.4, 'confidence_count' => 3]);
    analyticsRollup($beta, '2026-06-12 14:00:00', ['assistant_messages' => 6, 'unanswered_replies' => 2, 'confidence_sum' => 0.6, 'confidence_count' => 1]);
    // Outside the window.
    analyticsRollup($alpha, '2026-05-20 10:00:00', ['assistant_messages' => 9, 'unanswered_replies' => 9, 'confidence_sum' => 0.9, 'confidence_count' => 9]);
    // Another workspace.
    analyticsRollup(Agent::factory()->create(), '2026-06-10 09:00:00', ['assistant_messages' => 50, 'unanswered_replies' => 50, 'confidence_sum' => 5, 'confidence_count' => 50]);

    $account = app(SatisfactionStatsQuery::class)->get(sssScope($alpha));
    $alphaOnly = app(SatisfactionStatsQuery::class)->get(sssScope($alpha, $alpha->id));

    expect($account->aiReplies)->toBe(10)
        ->and($account->unanswered)->toBe(3)
        ->and($account->unansweredRatePct)->toBe(30.0)
        ->and($account->avgConfidence)->toBe(0.75)
        ->and($alphaOnly->aiReplies)->toBe(4)
        ->and($alphaOnly->unanswered)->toBe(1)
        ->and($alphaOnly->avgConfidence)->toBe(0.8);
});

it('has no average confidence when no reply in the window had one', function (): void {
    $agent = Agent::factory()->create();
    analyticsRollup($agent, '2026-06-10 09:00:00', ['assistant_messages' => 2]);

    $stats = app(SatisfactionStatsQuery::class)->get(sssScope($agent));

    expect($stats->aiReplies)->toBe(2)
        ->and($stats->unanswered)->toBe(0)
        ->and($stats->avgConfidence)->toBeNull();
});
