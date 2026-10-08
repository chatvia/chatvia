<?php

namespace Domain\Billing\Services\Entitlements;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Services\Billing;
use Domain\Billing\Services\UserCreditSystem;

/**
 * Entitlements when billing is on: the plan comes from the workspace's
 * Stripe subscription (Cashier's locally synced tables, never a Stripe API
 * call), message capacity from the cached {@see UserCreditSystem} flag, and
 * quotas from the `user_quotas` row with the default package as fallback.
 */
final readonly class StripeEntitlements implements Entitlements
{
    public function __construct(
        private UserCreditSystem $creditSystem,
        private Billing $plans,
    ) {}

    public function enforced(): bool
    {
        return true;
    }

    public function hasActiveBilling(User $user): bool
    {
        $billing = $user->accountBilling();

        return (bool) ($billing?->subscribed() || $billing?->onTrial());
    }

    public function hasMessageCapacity(int $workspaceId): bool
    {
        return $this->creditSystem->forWorkspace($workspaceId)->canSendMessages();
    }

    public function availableMessages(User $user): int
    {
        return $user->creditSystem()->consumableMessages(refresh: true);
    }

    public function capabilities(User $user): BillingPackageCapabilitiesData
    {
        // Read the parent's `plan` (or this account's package) rather than the
        // user's own `plan` accessor: `plan` and `capabilities` are both
        // appended attributes, and reading one from inside the other recurses.
        $plan = $user->workspace_id
            ? $user->parentWorkspace->plan
            : $user->getCurrentPackage()['id'];

        return BillingPackageCapabilitiesData::forPlan($plan);
    }

    public function quotaLimit(User $user, string $key): int
    {
        // A brand-new account has no quota row until the first plan grant
        // lands, so fall back to the default package's limit.
        return (int) ($user->quota?->{$key} ?? $this->plans->defaultFixedLimit($key));
    }
}
