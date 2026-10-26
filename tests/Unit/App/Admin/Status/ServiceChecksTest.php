<?php

use App\Admin\Status\SchedulerHeartbeat;
use App\Admin\Status\Service;
use App\Admin\Status\ServiceChecks;
use App\Admin\Status\ServiceState;

/**
 * The checks behind the admin panel's Status page, one per service. The
 * scheduler is running while its heartbeat is at most a missed minute old,
 * and down once it misses more. While emails only go to the log, the
 * sign-in links in them are readable there, which the email check says.
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

it('counts a scheduler that never woke up as down', function (): void {
    expect(app(ServiceChecks::class)->check(Service::Scheduler))
        ->state->toBe(ServiceState::Down)
        ->summary->toBe('Has not run a task yet.');
});

it('warns that sign-in links land in the log while emails go to the log', function (): void {
    config(['mail.default' => 'log']);

    expect(app(ServiceChecks::class)->check(Service::Email))
        ->state->toBe(ServiceState::Warning)
        ->summary->toBe('Emails go to the log.')
        ->hint->toContain('sign-in and password links');
});
