<?php

use App\Admin\Status\SchedulerHeartbeat;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;

/**
 * The scheduler check of the admin panel's Status page: running while its
 * heartbeat is at most a missed minute old, and down once it misses more.
 */
it('shows the scheduler as running within a minute of its start, and as stopped once it misses its runs', function (int $minutesAgo, ServiceState $state): void {
    $this->travelTo(now()->subMinutes($minutesAgo));
    SchedulerHeartbeat::record();
    $this->travelTo(now()->addMinutes($minutesAgo));

    expect(app(ServiceChecks::class)->check(Service::Scheduler)->state)->toBe($state);
})->with([
    'one missed minute' => [2, ServiceState::Running],
    'stopped' => [4, ServiceState::Down],
]);
