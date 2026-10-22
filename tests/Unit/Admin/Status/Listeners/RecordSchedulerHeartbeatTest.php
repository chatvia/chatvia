<?php

use App\Admin\Status\Listeners\RecordSchedulerHeartbeat;
use App\Admin\Status\SchedulerHeartbeat;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

function rshStarting(string $command): CommandStarting
{
    return new CommandStarting($command, new ArrayInput([]), new NullOutput);
}

it('notes each start of schedule:run', function (): void {
    event(rshStarting('schedule:run'));

    expect(SchedulerHeartbeat::lastRunAt())->not->toBeNull();
});

it('ignores every other command', function (): void {
    event(rshStarting('schedule:work'));
    event(rshStarting('horizon'));

    expect(SchedulerHeartbeat::lastRunAt())->toBeNull();
});

it('lets the scheduler run when the cache cannot be reached', function (): void {
    Cache::shouldReceive('forever')->once()->andThrow(new RuntimeException('Connection refused'));

    expect(fn () => (new RecordSchedulerHeartbeat)->handle(rshStarting('schedule:run')))->not->toThrow(Throwable::class);
});
