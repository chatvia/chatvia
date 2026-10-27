<?php

namespace Domain\Agent\Jobs;

use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Removes a deleted agent's data off the request: a busy agent has millions
 * of chat rows, far more than an HTTP request or a short queue job can delete.
 * Runs on the long-running `exports` queue.
 */
#[Queue('exports')]
// Each step deletes only what is left, so a retry carries on where the last try stopped.
#[Tries(3)]
// Seconds to wait before each retry, so a short storage or cache outage is over by the next try.
#[Backoff(60, 600)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
class PurgeAgentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $agentId) {}

    public function handle(PurgeAgentAction $purge): void
    {
        $agent = Agent::query()->onlyTrashed()->find($this->agentId);

        if ($agent === null) {
            return; // already purged (e.g. a retry after completion)
        }

        $purge->execute($agent);
    }
}
