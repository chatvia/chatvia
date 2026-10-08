<?php

namespace Domain\Billing\Services;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\MessageAdmission;

/**
 * The per-turn send gate: may this workspace receive one more assistant reply?
 * Every surface that answers a visitor (the web chat stream and the messaging
 * channels) asks this one gate, so they apply the same rule, admins included.
 *
 * Two checks, in order:
 *  1. Active billing: a paid subscription or a running trial. Admin accounts
 *     skip this check.
 *  2. Capacity: the cached can-send flag kept by {@see UserCreditSystem}. That
 *     flag also folds in the billing state whenever it is recomputed, so an
 *     admin account without a subscription is still refused here once the
 *     flag is refreshed.
 *
 * Pass the workspace owner (`$agent->user`), never the acting user.
 */
final readonly class MessageSendGate
{
    public function __construct(
        private UserCreditSystem $creditSystem,
    ) {}

    public function admit(User $owner): MessageAdmission
    {
        if (! $owner->isAdmin() && ! $this->hasActiveBilling($owner)) {
            return MessageAdmission::SubscriptionInactive;
        }

        if (! $this->creditSystem->forWorkspace($owner->id)->canSendMessages()) {
            return MessageAdmission::CreditsExhausted;
        }

        return MessageAdmission::Allowed;
    }

    /**
     * Read from Cashier's locally synced tables — a DB read, never a Stripe
     * API call. Checked on every turn so an ended subscription is refused even
     * while the cached capacity flag is still warm.
     */
    private function hasActiveBilling(User $owner): bool
    {
        $billing = $owner->billing;

        return (bool) ($billing?->subscribed() || $billing?->onTrial());
    }
}
