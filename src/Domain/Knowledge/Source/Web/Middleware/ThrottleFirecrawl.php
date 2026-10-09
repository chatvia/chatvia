<?php

namespace Domain\Knowledge\Source\Web\Middleware;

use Closure;
use Domain\Knowledge\Ingestion\Jobs\Website\WebsiteUrlTrainJob;
use Illuminate\Support\Facades\Redis;

/**
 * Queue-job middleware that holds every FireCrawl-calling job to a single global
 * per-minute request budget, shared across all workers via a Redis duration
 * limiter.
 *
 * Website ingestion fans a {@see WebsiteUrlTrainJob} out per discovered URL,
 * and many queue workers may run those jobs at once — so a large site would
 * otherwise fire many concurrent `/scrape` calls and go past the FireCrawl
 * account's rate limit (HTTP 429 "Rate limit exceeded"). This caps the
 * combined rate no matter how many workers run.
 *
 * When the per-minute budget is exhausted the job is released back to the queue
 * with a short jittered delay instead of calling FireCrawl. These throttle
 * releases must NOT count against the job's retry budget — the host job uses
 * {@see WebsiteUrlTrainJob::retryUntil()}
 * plus `$maxExceptions` so only genuine errors fail it.
 *
 * A configured rate of 0 disables throttling (the limiter is bypassed entirely).
 */
class ThrottleFirecrawl
{
    public function handle(object $job, Closure $next): void
    {
        $perMinute = (int) config('services.firecrawl.rate_per_minute', 0);

        if ($perMinute <= 0) {
            $next($job);

            return;
        }

        Redis::throttle('firecrawl:requests')
            ->allow($perMinute)
            ->every(60)
            ->then(
                fn () => $next($job),
                fn () => $job->release(random_int(5, 20)),
            );
    }
}
