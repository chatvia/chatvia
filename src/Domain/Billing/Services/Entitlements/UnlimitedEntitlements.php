<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Entitlements;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;

/**
 * Entitlements when billing is off (paid plans off in the admin panel, the
 * default of a new install): every workspace may always send messages, has
 * every feature, and has no quota limits. Conversations are kept for
 * `billing.unlimited_retention_days`.
 */
final readonly class UnlimitedEntitlements implements Entitlements
{
    public function enforced(): bool
    {
        return false;
    }

    public function hasActiveBilling(User $user): bool
    {
        return true;
    }

    public function hasMessageCapacity(int $workspaceId): bool
    {
        return true;
    }

    public function availableMessages(User $user): ?int
    {
        return null;
    }

    public function capabilities(User $user): BillingPackageCapabilitiesData
    {
        return BillingPackageCapabilitiesData::unlimited(
            max(1, (int) config('billing.unlimited_retention_days', 366)),
        );
    }

    public function quotaLimit(User $user, string $key): int
    {
        return self::UNLIMITED;
    }
}
