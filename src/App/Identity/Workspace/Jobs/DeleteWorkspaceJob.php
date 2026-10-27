<?php

namespace App\Identity\Workspace\Jobs;

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

/**
 * Off-request workspace teardown — a mature workspace deletes millions of
 * chunk/message rows, far past any HTTP timeout. Runs on the long-running
 * `exports` queue. The account is deactivated before dispatch so the user
 * can't re-enter mid-teardown.
 */
#[Queue('exports')]
// Every step is idempotent (skip-if-gone), so retries are safe.
#[Tries(3)]
// Seconds to wait before each retry, so a short storage or cache outage is over by the next try.
#[Backoff(60, 600)]
// Below the `redis-long` connection's `retry_after`, so no second worker picks it up mid-run.
#[Timeout(1800)]
class DeleteWorkspaceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $userId) {}

    public function handle(DeleteUserAccountAction $deleteAccount): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return; // already torn down (e.g. a retry after completion)
        }

        $deleteAccount->execute($user);
    }
}
