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

        // Daily spend ceiling. The hourly debounce alone still permits ~24
        // off-session card charges/day, which an abuse-driven quota drain
        // (e.g. anonymous traffic hammering a public agent) could sustain
        // indefinitely. Cap the number of auto-recharges in any rolling 24h so
        // a spend spike can't run away on the customer's card. Re-checked under
        // lock in ProcessAutoRechargeJob.
        if (self::dailyRechargeCount($user) >= (int) config('billing.addons.auto_recharge_credits.max_per_day', 3)) {
            return;
        }

        ProcessAutoRechargeJob::dispatch($user->id);
    }

    /** Count auto-recharge addons granted to this account in the last 24h. */
    public static function dailyRechargeCount(User $user): int
    {
        return $user->addons()
            ->where('type', 'auto_recharge_credits')
            ->where('started_at', '>=', now()->subDay())
            ->count();
    }
}
