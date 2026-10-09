<?php

namespace Domain\Billing\Events;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Webhooks\ProvisionTrialWorkspaceAction;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A completed trial checkout created a brand-new workspace account
 * ({@see ProvisionTrialWorkspaceAction}). Not fired for a returning customer
 * whose existing account was linked instead.
 */
class TrialWorkspaceProvisioned
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly string $package,
    ) {}
}
