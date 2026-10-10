<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Credits\StartCreditCycleAction;
use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Grants the quotas and message credits a paid plan entitles a workspace to.
 *
 * This is the core "what a paid plan grants" rule. It runs for new
 * subscriptions (trial start) and for every renewal; the Stripe webhook only
 * works out which plan was paid for and calls this, so the rule can be tested
 * and reused (for example by a manual grant) without faking a webhook.
 *
 * The synced Cashier subscription (its Stripe price) stays the source of truth
 * for the plan; `user_billing.plan` is a convenience label written on every
 * grant.
 */
class GrantSubscriptionAction
{
    /** The fixed quota columns a plan sets on `user_quotas`. */
    private const array FIXED_QUOTA_COLUMNS = [
        'agents_limit',
        'pages_limit',
        'minutes_limit',
        'custom_domains_limit',
        'workspace_users_limit',
        'links_limit',
        'teams_limit',
    ];

    public function __construct(
        private readonly StartCreditCycleAction $startCreditCycle,
        private readonly UserCreditSystem $creditSystem,
        private readonly BillingCatalog $catalog,
    ) {}

    public function execute(User $user, PlanSelectionData $plan): void
    {
        $cycle = $plan->isYearly ? 'yearly' : 'monthly';

        $quotaLimits = $this->catalog->plan($plan->package)?->quota_limits ?? [];
        $cycleLimits = $quotaLimits[$cycle] ?? null;
        $fixedLimits = $quotaLimits['fixed'] ?? null;

        if (! $cycleLimits || ! $fixedLimits) {
            Log::warning('Invalid package configuration', ['package' => $plan->package, 'cycle' => $cycle]);

            return;
        }

        $endsAt = $plan->isYearly ? now()->addYear() : now()->addMonth();

        DB::transaction(function () use ($user, $plan, $fixedLimits, $cycleLimits, $endsAt): void {
            $user->billing()->updateOrCreate([], ['plan' => $plan->package]);

            // Plan quotas are fixed per package, never added up.
            $user->quota()->updateOrCreate([], array_intersect_key(
                $fixedLimits,
                array_flip(self::FIXED_QUOTA_COLUMNS),
            ));

            $this->startCreditCycle->execute($user, (int) $cycleLimits['messages_limit'], $endsAt);
        });

        $user->refresh();

        $this->creditSystem->forWorkspace($user->workspace()->id)->refreshCanSendMessages();
    }
}
