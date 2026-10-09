<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\UserBilling;

/**
 * The rules for charging a workspace's card off-session when its message
 * balance runs low. Every step of the flow asks this one class: the charge
 * Action (is the balance low enough to raise the alarm?), the listener (may we
 * queue a recharge?) and the recharge Action (re-checked under a lock).
 *
 * A recharge is allowed when the workspace turned auto-recharge on, was not
 * recharged in the last hour, and stays under the daily ceiling. The ceiling
 * stops a spend spike (for example anonymous traffic draining a public agent's
 * quota) from charging the card again and again.
 */
final readonly class AutoRechargePolicy
{
    /** Minimum time between two recharges of the same workspace. */
    private const int DEBOUNCE_MINUTES = 60;

    public function isEnabled(?UserBilling $billing): bool
    {
        return $billing !== null && $billing->auto_recharge_enabled;
    }

    /**
     * Whether the balance has fallen to the workspace's recharge threshold.
     */
    public function isBelowThreshold(UserBilling $billing, int $availableMessages): bool
    {
        return $this->isEnabled($billing) && $availableMessages <= $billing->auto_recharge_threshold;
    }

    /**
     * Whether a recharge may run now: turned on, not within the debounce
     * window, and under the daily ceiling.
     */
    public function mayRechargeNow(User $workspace): bool
    {
        $billing = $workspace->accountBilling();

        if (! $this->isEnabled($billing)) {
            return false;
        }

        if ($billing->auto_recharge_last_triggered_at?->gt(now()->subMinutes(self::DEBOUNCE_MINUTES))) {
            return false;
        }

        return $this->rechargesInLastDay($workspace) < $this->maxRechargesPerDay();
    }

    /**
     * How many credit units one recharge buys: the configured amount rounded
     * up to whole units, at least one.
     */
    public function unitsFor(UserBilling $billing): int
    {
        $unitMessages = MessageCreditAddon::AutoRechargeCredits->unitMessages();

        return max(1, (int) ceil($billing->auto_recharge_amount / $unitMessages));
    }

    private function rechargesInLastDay(User $workspace): int
    {
        return $workspace->addons()
            ->where('type', MessageCreditAddon::AutoRechargeCredits->value)
            ->where('started_at', '>=', now()->subDay())
            ->count();
    }

    private function maxRechargesPerDay(): int
    {
        return (int) config('billing.addons.auto_recharge_credits.max_per_day', 3);
    }
}
