<?php

namespace Domain\Billing\Jobs;

use Domain\Billing\Actions\Account\ConsumeMessageCreditsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateUserCreditsJob implements ShouldQueue
{
    use Queueable;

    /**
     * The `messages` Horizon supervisor runs with tries=1; billing increments
     * must survive a transient DB hiccup, so the job sets its own budget.
     */
    public int $tries = 10;

    public function __construct(
        public int $userId,
        public int $modelId,
        public int $additionalMessages = 0,
    ) {
        $this->onQueue('messages');
    }

    /**
     * Concurrent replies for the same workspace serialise on the Action's
     * `lockForUpdate()` over the users row — row-lock waits queue instead of
     * timing out. A Redis lock used to sit in front of it with `block(5)`;
     * under per-workspace bursts the 5s wait threw LockTimeoutException and
     * the increment was silently dropped (tries=1) — double-locking with a
     * worse failure mode for zero extra safety.
     */
    public function handle(ConsumeMessageCreditsAction $action): void
    {
        $action->execute($this->userId, $this->modelId, $this->additionalMessages);
    }
}
