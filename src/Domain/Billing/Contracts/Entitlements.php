<?php

namespace Domain\Billing\Contracts;

use App\Identity\Workspace\Concerns\InteractsWithWorkspaceQuotas;
use App\Identity\Workspace\Models\User;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Services\Entitlements\StripeEntitlements;
use Domain\Billing\Services\Entitlements\UnlimitedEntitlements;

/**
 * What a workspace may do: send messages, use plan features, and how far its
 * quotas reach. Callers ask this port instead of reading plans, credits or
 * quota rows directly, so billing can be switched off in one place.
 *
 * Two drivers, picked by `billing.enabled`:
 *  - {@see StripeEntitlements}: the plan from the Stripe subscription,
 *    message credits and quota rows.
 *  - {@see UnlimitedEntitlements}: a self-hosted install without billing —
 *    everything on, nothing limited.
 *
 * Every method that takes a `User` resolves through that user's workspace,
 * so an employee or chatter gets the answer of the account they belong to.
 */
interface Entitlements
{
    /**
     * The quota value that stands for "no limit". Large enough never to be
     * reached, small enough to fit a Postgres integer and a JavaScript number.
     */
    public const int UNLIMITED = 1_000_000_000;

    /**
     * Whether plans are enforced at all: subscriptions, message credits and
     * quotas. False when billing is turned off.
     */
    public function enforced(): bool;

    /**
     * Whether the workspace has a paid subscription or a running trial.
     */
    public function hasActiveBilling(User $user): bool;

    /**
     * Whether the workspace's message pool has room for one more reply.
     */
    public function hasMessageCapacity(int $workspaceId): bool;

    /**
     * Messages left in the workspace's pool this cycle, or null when there
     * is no limit.
     */
    public function availableMessages(User $user): ?int;

    /**
     * The plan features the workspace has.
     */
    public function capabilities(User $user): BillingPackageCapabilitiesData;

    /**
     * The plan's limit for one fixed quota, e.g. 'teams_limit' or
     * 'pages_limit'. Add-on extras (extra agents, members, custom domains)
     * are not included; {@see InteractsWithWorkspaceQuotas} adds them for the
     * totals.
     */
    public function quotaLimit(User $user, string $key): int;
}
