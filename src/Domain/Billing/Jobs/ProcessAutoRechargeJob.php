<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Actions\AutoRecharge\ProcessAutoRechargeAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued off-session recharge of a workspace's message credits. Runs on the
 * `messages` queue; all the rules and the charge live in
 * {@see ProcessAutoRechargeAction}.
 */
class ProcessAutoRechargeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId)
    {
        $this->onQueue('messages');
    }

    public function handle(ProcessAutoRechargeAction $action): void
    {
        $action->execute($this->userId);
    }
}
