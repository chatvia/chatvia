<?php

use App\Admin\Status\SchedulerHeartbeat;

/**
 * The scheduler's heartbeat: the moment `schedule:run` last started, which
 * the Status page reads to tell a running scheduler from a stopped one
 * (ServiceChecksTest).
 */
it('knows no run before the scheduler first wakes up', function (): void {
    expect(SchedulerHeartbeat::lastRunAt())->toBeNull();
});

it('remembers when the scheduler last woke up', function (): void {
    $this->freezeTime();

    SchedulerHeartbeat::record();

    expect(SchedulerHeartbeat::lastRunAt()?->getTimestamp())->toBe(now()->getTimestamp());
});
