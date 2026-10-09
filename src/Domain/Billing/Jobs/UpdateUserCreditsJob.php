<?php

namespace Domain\Billing\Jobs;

use Domain\Billing\Actions\Credits\ConsumeMessageCreditsAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * Charges the workspace for one assistant reply. Runs on the `messages` queue
 * and is retried on failure, so every dispatch carries its own charge key: a
 * retry of the same job reuses the key and the Action skips the increment it
 * already made. A job queued before the key existed has none and is charged
 * as before.
 */
class UpdateUserCreditsJob implements ShouldQueue
{
    use Queueable;

    /** The messages supervisor runs tries=1; billing must survive transient failures. */
    public int $tries = 10;

    /**
     * Idempotency key for this charge. A plain property with a default (not a
     * promoted one) so a job serialised before it existed still unserialises.
     */
    public ?string $chargeKey = null;

    public function __construct(
        public int $userId,
        public int $modelId,
        public int $additionalMessages = 0,
        ?string $chargeKey = null,
    ) {
        $this->chargeKey = $chargeKey ?? (string) Str::uuid();

        $this->onQueue('messages');
    }

    /** Concurrent replies serialise on the Action's lockForUpdate() over the users row. */
    public function handle(ConsumeMessageCreditsAction $action): void
    {
        $action->execute($this->userId, $this->modelId, $this->additionalMessages, $this->chargeKey);
    }
}
