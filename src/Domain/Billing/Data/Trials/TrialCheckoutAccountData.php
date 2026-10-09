<?php

namespace Domain\Billing\Data\Trials;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Trials\ResolveTrialCheckoutAccountAction;

/**
 * What {@see ResolveTrialCheckoutAccountAction} found for a finished trial
 * checkout: the account it belongs to (null while the webhook has not created
 * it yet) and whether the visitor may be signed in to it automatically.
 */
final readonly class TrialCheckoutAccountData
{
    public function __construct(
        public ?User $user,
        public bool $maySignInAutomatically,
    ) {}
}
