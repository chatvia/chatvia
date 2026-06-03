<?php

namespace Domain\Billing\Listeners;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Events\MessageCreditsLow;
use Domain\Billing\Jobs\ProcessAutoRechargeJob;

/**
 * Bridges the credits-low event to the off-session recharge job. Skips
 * silently when the user has no billing row, has not enabled auto-recharge,
 * or has already been recharged in the last hour (re-fire safety net).
 */
class TriggerAutoRecharge
{
    public function handle(MessageCreditsLow $event): void
    {
        /** @var User|null $user */
        $user = User::find($event->userId);

        if (! $user) {
            return;
        }

        $billing = $user->accountBilling();

        if (! $billing || ! $billing->auto_recharge_enabled) {
            return;
        }

        // Debounce: don't fire twice within an hour. The credits-low event
        // can be dispatched many times in quick succession (every consumed
        // message under the threshold) — one recharge per hour is enough.
        if ($billing->auto_recharge_last_triggered_at
            && $billing->auto_recharge_last_triggered_at->gt(now()->subHour())) {
            return;
        }

        ProcessAutoRechargeJob::dispatch($user->id);
    }
}
