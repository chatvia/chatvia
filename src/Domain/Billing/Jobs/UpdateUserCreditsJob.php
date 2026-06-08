<?php

namespace Domain\Billing\Jobs;

use Domain\Billing\Actions\Account\ConsumeMessageCreditsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class UpdateUserCreditsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $userId,
        public int $modelId,
        public int $additionalMessages = 0,
    ) {
        $this->onQueue('messages');
    }

    /**
     * Serialise concurrent replies for the same user with a distributed lock,
     * then delegate the credit-consumption logic to the Action. The lock is
     * the job's only responsibility; the billing rule lives in the Action.
     */
    public function handle(ConsumeMessageCreditsAction $action): void
    {
        Cache::lock("user_credits_lock:{$this->userId}", 10)
            ->block(5, fn () => $action->execute($this->userId, $this->modelId, $this->additionalMessages));
    }
}
