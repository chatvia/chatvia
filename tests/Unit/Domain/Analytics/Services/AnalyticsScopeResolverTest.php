<?php

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Services\AnalyticsScopeResolver;

/**
 * The scope of an account-analytics question: the window and the agents whose
 * numbers count. Only the workspace's own agents can narrow it.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 0, 0));

    $this->workspace = User::factory()->create()->workspace();
    $this->alpha = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->beta = Agent::factory()->create(['workspace_id' => $this->workspace->id]);
});

it('covers the whole account by default, over this month', function (): void {
    $scope = app(AnalyticsScopeResolver::class)->resolve($this->workspace, new AnalyticsFilterData);

    expect($scope->agentId)->toBeNull()
        ->and($scope->agentIds)->toEqualCanonicalizing([$this->alpha->id, $this->beta->id])
        ->and($scope->period->named)->toBe(AnalyticsPeriod::ThisMonth)
        ->and($scope->period->start->toDateString())->toBe('2026-06-01');
});

it('narrows to one of the workspace\'s agents', function (): void {
    $scope = app(AnalyticsScopeResolver::class)->resolve(
        $this->workspace,
        new AnalyticsFilterData(period: AnalyticsPeriod::Last7Days, agentExternalId: $this->beta->external_id),
    );

    expect($scope->agentId)->toBe($this->beta->id)
        ->and($scope->agentIds)->toBe([$this->beta->id])
        ->and($scope->period->named)->toBe(AnalyticsPeriod::Last7Days)
        ->and($scope->period->start->toDateString())->toBe('2026-06-09');
});

it('narrows another workspace\'s agent to nothing', function (): void {
    $foreign = Agent::factory()->create();

    $scope = app(AnalyticsScopeResolver::class)->resolve(
        $this->workspace,
        new AnalyticsFilterData(agentExternalId: $foreign->external_id),
    );

    expect($scope->agentId)->toBe(AnalyticsScopeData::UNKNOWN_AGENT)
        ->and($scope->agentIds)->toBe([AnalyticsScopeData::UNKNOWN_AGENT]);
});
