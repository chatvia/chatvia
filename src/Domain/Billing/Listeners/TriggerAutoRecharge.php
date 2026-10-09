<?php

namespace Domain\Billing\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\ProcessAutoRechargeJob;
use Domain\Billing\Services\AutoRechargePolicy;

/**
 * Bridges the credits-low event to the off-session recharge job. The event can
 * fire for every message sent under the threshold, so the job is queued only
 * when the {@see AutoRechargePolicy} allows a recharge right now.
 */
class TriggerAutoRecharge
{
    public function __construct(
        private readonly AutoRechargePolicy $policy,
    ) {}

    public function handle(MessageCreditsLow $event): void
    {
        $workspace = User::find($event->userId);

        if ($workspace !== null && $this->policy->mayRechargeNow($workspace)) {
            ProcessAutoRechargeJob::dispatch($workspace->id);
        }
    }
}
