<?php

use App\Admin\Status\SchedulerHeartbeat;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;

/**
 * The checks behind the admin panel's Status page, one per service. The
 * scheduler is running while its heartbeat is at most a missed minute old,
 * and down once it misses more.
 */
it('shows the scheduler as running within a minute of its start, and as stopped once it misses its runs', function (int $minutesAgo, ServiceState $state): void {
    $this->travelTo(now()->subMinutes($minutesAgo));
    SchedulerHeartbeat::record();
    $this->travelTo(now()->addMinutes($minutesAgo));

    expect(app(ServiceChecks::class)->check(Service::Scheduler)->state)->toBe($state);
})->with([
    'just now' => [0, ServiceState::Running],
    'one missed minute' => [2, ServiceState::Running],
    'stopped' => [4, ServiceState::Down],
]);

it('says the scheduler has not run yet when it never woke up', function (): void {
    expect(app(ServiceChecks::class)->check(Service::Scheduler))
        ->state->toBe(ServiceState::Warning)
        ->summary->toBe('Has not run a task yet.');
});
