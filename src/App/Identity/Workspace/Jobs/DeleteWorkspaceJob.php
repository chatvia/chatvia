<?php

namespace App\Identity\Workspace\Jobs;

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Off-request workspace teardown — a mature workspace deletes millions of
 * chunk/message rows, far past any HTTP timeout. The account is deactivated
 * before dispatch so the user can't re-enter mid-teardown.
 */
class DeleteWorkspaceJob implements ShouldQueue
{
    use Queueable;

    /** Every step is idempotent (skip-if-gone), so retries are safe. */
    public int $tries = 3;

    /**
     * Seconds to wait before each retry. A failed Stripe cancel stops the
     * teardown, and a short Stripe outage should be over by the next try.
     *
     * @var list<int>
     */
    public array $backoff = [60, 600];

    public int $timeout = 3600;

    public function __construct(public int $userId) {}

    public function handle(DeleteUserAccountAction $deleteAccount): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return; // already torn down (e.g. a retry after completion)
        }

        $deleteAccount->execute($user);
    }
}
