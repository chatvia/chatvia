<?php

namespace Domain\Analytics\Queries\Account\Usage;

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Account\Usage\BillingCycleUsageData;

/**
 * The current billing cycle's message allowance and a straight-line
 * projection of where usage will land by the cycle end. Billing is
 * message-based; tokens are reported for analytics only. Null when the
 * workspace has no active cycle.
 */
class BillingCycleUsageQuery
{
    public function get(User $workspace): ?BillingCycleUsageData
    {
        $credit = $workspace->credit;
        if (! $credit) {
            return null;
        }

        $usage = $credit->usage;
        $now = CarbonImmutable::now();
        $start = CarbonImmutable::parse($credit->started_at);
        $end = CarbonImmutable::parse($credit->ends_at);

        $limit = (int) $credit->messages;
        $used = (int) ($usage->messages ?? 0);

        $daysTotal = max(1, (int) $start->diffInDays($end));
        $daysElapsed = max(1, min($daysTotal, (int) $start->diffInDays($now)));

        return new BillingCycleUsageData(
            cycleStart: $start,
            cycleEnd: $end,
            daysTotal: $daysTotal,
            daysLeft: max(0, (int) $now->diffInDays($end, false)),
            messagesLimit: $limit,
            messagesUsed: $used,
            messagesRemaining: max(0, $limit - $used),
            messagesExceeded: (int) ($usage->messages_exceeded ?? 0),
            addonMessages: $this->activeAddonMessages($workspace, $now),
            tokensUsed: (int) ($usage->tokens ?? 0),
            projectedMessagesUsed: (int) round(($used / $daysElapsed) * $daysTotal),
        );
    }

    /** Add-on messages from add-ons that have not ended. */
    private function activeAddonMessages(User $workspace, CarbonImmutable $now): int
    {
        return (int) $workspace->addons()
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->sum('amount');
    }
}
