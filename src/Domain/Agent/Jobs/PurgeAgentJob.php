<?php

namespace Domain\Agent\Jobs;

use Domain\Agent\Actions\Account\PurgeAgentAction;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Removes a deleted agent's data off the request: a busy agent has millions
 * of chat rows, far more than an HTTP request or a short queue job can delete.
 * Runs on the long-running `exports` queue.
 */
class PurgeAgentJob implements ShouldQueue
{
    use Queueable;

    /** Each step deletes only what is left, so a retry carries on where the last try stopped. */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry, so a short storage or cache outage
     * is over by the next try.
     *
     * @var list<int>
     */
    public array $backoff = [60, 600];

    /** Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run. */
    public int $timeout = 1800;

    public function __construct(public int $agentId)
    {
        $this->onQueue('exports');
    }

    public function handle(PurgeAgentAction $purge): void
    {
        $agent = Agent::onlyTrashed()->find($this->agentId);

        if ($agent === null) {
            return; // already purged (e.g. a retry after completion)
        }

        $purge->execute($agent);
    }
}
