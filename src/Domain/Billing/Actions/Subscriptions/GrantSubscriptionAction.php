<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Grants the quotas and message credits a paid plan entitles a workspace to.
 *
 * This is the core "what a paid plan grants" business rule. It runs for both
 * new subscriptions (trial start) and renewals; the Stripe webhook listener is
 * the thin adapter that parses the payload and calls this. Keeping the rule
 * here makes it directly unit-testable and reusable by any future manual /
 * admin grant flow without faking a Stripe webhook.
 *
 * Source of truth for entitlement remains the synced Cashier subscription
 * (stripe_price); `user_billing.plan` is a denormalised convenience label
 * written on every grant.
 */
class GrantSubscriptionAction
{
    public function execute(User $user, string $package, bool $isYearly): void
    {
        $cycle = $isYearly ? 'yearly' : 'monthly';

        $quotaLimit = config('billing.packages.'.$package.'.quota_limits.'.$cycle);
        $fixedLimit = config('billing.packages.'.$package.'.quota_limits.fixed');

        if (! $quotaLimit || ! $fixedLimit) {
            Log::warning('Invalid package configuration', ['package' => $package, 'cycle' => $cycle]);

            return;
        }

        $endsAt = $isYearly ? now()->addYear() : now()->addMonth();

        DB::transaction(function () use ($user, $package, $fixedLimit, $quotaLimit, $endsAt): void {
            // Persist the denormalised plan label (written on trial start + each
            // renewal, which passes the plan derived from the Stripe price).
            $user->billing()->updateOrCreate([], ['plan' => $package]);

            // Set quota limits (fixed per package, not cumulative).
            $user->quota()->updateOrCreate([], [
                'agents_limit' => $fixedLimit['agents_limit'],
                'pages_limit' => $fixedLimit['pages_limit'],
                'minutes_limit' => $fixedLimit['minutes_limit'],
                'custom_domains_limit' => $fixedLimit['custom_domains_limit'],
                'workspace_users_limit' => $fixedLimit['workspace_users_limit'],
                'links_limit' => $fixedLimit['links_limit'],
                'teams_limit' => $fixedLimit['teams_limit'],
            ]);

            // Add message credits for the billing period.
            $user->creditSystem()->addCredit($quotaLimit, $endsAt);
        });

        $user->refresh();

        $user->creditSystem()->refreshCanSendMessages();
    }
}
