<?php

namespace Domain\Analytics\Services;

use App\Identity\Workspace\Models\User;
use Carbon\CarbonImmutable;
use Domain\Analytics\Data\Account\AnalyticsFilterData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Billing\Models\UserCredit;

/**
 * Turns an analytics filter's `period` (plus optional explicit start/end
 * dates, which win when both are given) into a concrete {@see Period} for a
 * given workspace. The default and most important window is `current_cycle`,
 * derived from the workspace owner's active `user_credits` row (the billing
 * period), so "this cycle" questions line up exactly with what the customer
 * is billed on.
 */
class AnalyticsPeriodResolver
{
    public function resolve(User $workspace, AnalyticsFilterData $filter): Period
    {
        if (filled($filter->startDate) && filled($filter->endDate)) {
            return new Period(
                CarbonImmutable::parse($filter->startDate)->startOfDay(),
                CarbonImmutable::parse($filter->endDate)->endOfDay(),
                'the selected range',
            );
        }

        $now = CarbonImmutable::now();
        $named = $filter->period ?? AnalyticsPeriod::CurrentCycle;

        return match ($named) {
            AnalyticsPeriod::Today => new Period($now->startOfDay(), $now, 'today', $named),
            AnalyticsPeriod::Last7Days => new Period($now->subDays(6)->startOfDay(), $now, 'the last 7 days', $named),
            AnalyticsPeriod::Last30Days => new Period($now->subDays(29)->startOfDay(), $now, 'the last 30 days', $named),
            AnalyticsPeriod::ThisMonth => new Period($now->startOfMonth(), $now, 'this month', $named),
            AnalyticsPeriod::LastMonth => new Period(
                $now->subMonthNoOverflow()->startOfMonth(),
                $now->subMonthNoOverflow()->endOfMonth(),
                'last month',
                $named,
            ),
            AnalyticsPeriod::AllTime => new Period(CarbonImmutable::create(2020, 1, 1), $now, 'all time', $named),
            AnalyticsPeriod::PreviousCycle => $this->previousCycle($workspace, $now),
            AnalyticsPeriod::CurrentCycle => $this->currentCycle($workspace, $now),
        };
    }

    private function currentCycle(User $workspace, CarbonImmutable $now): Period
    {
        $credit = $workspace->credit;

        if ($credit) {
            return new Period(
                CarbonImmutable::parse($credit->started_at),
                CarbonImmutable::parse($credit->ends_at),
                'this billing cycle',
                AnalyticsPeriod::CurrentCycle,
            );
        }

        // No active credit row (e.g. brand-new or lapsed account) — fall back to
        // the calendar month so the tools still return something sensible.
        return new Period($now->startOfMonth(), $now, 'this billing cycle', AnalyticsPeriod::CurrentCycle);
    }

    private function previousCycle(User $workspace, CarbonImmutable $now): Period
    {
        $current = $workspace->credit;
        $currentStart = $current ? CarbonImmutable::parse($current->started_at) : $now->startOfMonth();

        /** @var UserCredit|null $previous */
        $previous = $workspace->credits()
            ->where('ends_at', '<', $currentStart)
            ->orderByDesc('ends_at')
            ->first();

        if ($previous) {
            return new Period(
                CarbonImmutable::parse($previous->started_at),
                CarbonImmutable::parse($previous->ends_at),
                'the previous billing cycle',
                AnalyticsPeriod::PreviousCycle,
            );
        }

        // No earlier credit row on file — derive a same-length window ending
        // where the current cycle began.
        $length = $current
            ? (int) CarbonImmutable::parse($current->started_at)->diffInDays(CarbonImmutable::parse($current->ends_at))
            : 30;

        return new Period(
            $currentStart->subDays($length + 1),
            $currentStart->subSecond(),
            'the previous billing cycle',
            AnalyticsPeriod::PreviousCycle,
        );
    }
}
