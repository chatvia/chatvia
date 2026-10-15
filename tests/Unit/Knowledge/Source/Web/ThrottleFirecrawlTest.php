<?php

use Domain\Knowledge\Source\Web\Middleware\ThrottleFirecrawl;
use Illuminate\Support\Facades\Redis;

/**
 * The job middleware that holds every FireCrawl-calling job to a global
 * per-minute budget via a Redis duration limiter, so a fan-out of scrape jobs
 * across many Horizon workers can't burst past the plan's rate limit (429).
 */
it('passes straight through when the rate is disabled (0), never touching the limiter', function (): void {
    config()->set('services.firecrawl.rate_per_minute', 0);

    Redis::shouldReceive('throttle')->never();

    $job = new stdClass;
    $ran = null;

    (new ThrottleFirecrawl)->handle($job, function (object $passed) use (&$ran): void {
        $ran = $passed;
    });

    expect($ran)->toBe($job);
});

it('runs the job when the limiter grants a slot', function (): void {
    config()->set('services.firecrawl.rate_per_minute', 60);

    $builder = Mockery::mock();
    $builder->shouldReceive('allow')->with(60)->andReturnSelf();
    $builder->shouldReceive('every')->with(60)->andReturnSelf();
    // Limiter has budget → invoke the success callback.
    $builder->shouldReceive('then')->andReturnUsing(fn (Closure $onAllow): mixed => $onAllow());

    Redis::shouldReceive('throttle')->with('firecrawl:requests')->andReturn($builder);

    $ran = false;

    (new ThrottleFirecrawl)->handle(new stdClass, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeTrue();
});

it('releases the job back to the queue (without running it) when the budget is spent', function (): void {
    config()->set('services.firecrawl.rate_per_minute', 60);

    $builder = Mockery::mock();
    $builder->shouldReceive('allow')->andReturnSelf();
    $builder->shouldReceive('every')->andReturnSelf();
    // Budget exhausted → invoke the failure callback instead of the job.
    $builder->shouldReceive('then')->andReturnUsing(fn (Closure $onAllow, Closure $onThrottled): mixed => $onThrottled());

    Redis::shouldReceive('throttle')->andReturn($builder);

    $job = Mockery::mock();
    $job->shouldReceive('release')->once()->withArgs(fn (mixed $delay): bool => is_int($delay) && $delay > 0);

    $ran = false;

    (new ThrottleFirecrawl)->handle($job, function () use (&$ran): void {
        $ran = true;
    });

    expect($ran)->toBeFalse();
});
