<?php

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Services\AnalyticsScopeResolver;
use Support\Enums\User\UserType;

/**
 * The scope of an account-analytics question: the window and the agents whose
 * numbers count. Only the workspace's own agents can narrow it.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::create(2026, 6, 15, 12, 0, 0));

    $this->workspace = User::factory()->create(['type' => UserType::Workspace]);
    $this->alpha = Agent::factory()->create(['user_id' => $this->workspace->id]);
    $this->beta = Agent::factory()->create(['user_id' => $this->workspace->id]);
});

it('covers the whole account by default, over the current cycle', function (): void {
    $scope = app(AnalyticsScopeResolver::class)->resolve($this->workspace, new AnalyticsFilterData);

    expect($scope->agentId)->toBeNull()
        ->and($scope->agentIds)->toEqualCanonicalizing([$this->alpha->id, $this->beta->id])
        ->and($scope->period->key)->toBe('current_cycle');
});

it('narrows to one of the workspace\'s agents', function (): void {
    $scope = app(AnalyticsScopeResolver::class)->resolve(
        $this->workspace,
        new AnalyticsFilterData(period: AnalyticsPeriod::Last7Days, agentExternalId: $this->beta->external_id),
    );

    expect($scope->agentId)->toBe($this->beta->id)
        ->and($scope->agentIds)->toBe([$this->beta->id])
        ->and($scope->period->key)->toBe('last_7_days')
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

it('lets explicit dates win over the named period', function (): void {
    $scope = app(AnalyticsScopeResolver::class)->resolve(
        $this->workspace,
        new AnalyticsFilterData(period: AnalyticsPeriod::AllTime, startDate: '2026-03-01', endDate: '2026-03-31'),
    );

    expect($scope->period->key)->toBe('custom')
        ->and($scope->period->start->toDateTimeString())->toBe('2026-03-01 00:00:00')
        ->and($scope->period->end->toDateTimeString())->toBe('2026-03-31 23:59:59');
});
