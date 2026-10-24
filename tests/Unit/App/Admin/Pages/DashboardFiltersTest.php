<?php

use App\Admin\Pages\DashboardFilters;
use Illuminate\Support\Carbon;

/**
 * The admin dashboard's filters as one platform scope: a custom date range
 * reads either way round, and shows this month until both dates are picked.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('reads a custom date range, either way round', function (): void {
    $scope = DashboardFilters::scope([
        DashboardFilters::PERIOD => DashboardFilters::CUSTOM,
        DashboardFilters::START_DATE => '2031-03-12',
        DashboardFilters::END_DATE => '2031-03-05',
    ]);

    expect($scope->period->start->toDateTimeString())->toBe('2031-03-05 00:00:00')
        ->and($scope->period->end->toDateTimeString())->toBe('2031-03-12 23:59:59');
});

it('shows this month until both dates of a custom range are picked', function (): void {
    $scope = DashboardFilters::scope([DashboardFilters::PERIOD => DashboardFilters::CUSTOM, DashboardFilters::START_DATE => '2031-03-12']);

    expect($scope->period->start->toDateTimeString())->toBe('2031-03-01 00:00:00');
});
