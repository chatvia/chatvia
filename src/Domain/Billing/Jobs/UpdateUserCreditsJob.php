<?php

namespace Domain\Billing\Jobs;

use Domain\Billing\Actions\Account\ConsumeMessageCreditsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class UpdateUserCreditsJob implements ShouldQueue
{
    use Queueable;

    /** The messages supervisor runs tries=1; billing must survive transient failures. */
    public int $tries = 10;

    public function __construct(
        public int $userId,
        public int $modelId,
        public int $additionalMessages = 0,
    ) {
        $this->onQueue('messages');
    }

    /** Concurrent replies serialise on the Action's lockForUpdate() over the users row. */
    public function handle(ConsumeMessageCreditsAction $action): void
    {
        $action->execute($this->userId, $this->modelId, $this->additionalMessages);
    }
}
