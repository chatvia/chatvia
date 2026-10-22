<?php

use App\Admin\Status\SchedulerHeartbeat;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;

it('knows no run before the scheduler first wakes up', function (): void {
    expect(SchedulerHeartbeat::lastRunAt())->toBeNull();
});

it('remembers when the scheduler last woke up', function (): void {
    $this->freezeTime();

    SchedulerHeartbeat::record();

    expect(SchedulerHeartbeat::lastRunAt()?->getTimestamp())->toBe(now()->getTimestamp());
});

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
