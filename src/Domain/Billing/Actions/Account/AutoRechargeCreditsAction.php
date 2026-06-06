<?php

namespace Domain\Billing\Actions\Account;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserAddon;
use Illuminate\Support\Facades\DB;

/**
 * Charges the user's default payment method off-session for a Stripe price
 * (the extra-messages SKU) and records the matching `user_addons` row.
 *
 * Distinct from `BuyExtraMessageCreditsAction`, which builds a hosted
 * Stripe Checkout (on-session, redirect). This action is invoked from the
 * `ProcessAutoRechargeJob`, where there is no browser session to redirect.
 */
class AutoRechargeCreditsAction
{
    public function execute(User $user, int $units): ?UserAddon
    {
        $billing = $user->accountBillingOrFail();

        $priceId = config('billing.addons.auto_recharge_credits.stripe_price_id');
        $unitMessages = (int) config('billing.addons.auto_recharge_credits.unit_messages', 1000);

        if (! $priceId) {
            return null;
        }

        // Reserve the 1-hour debounce BEFORE charging, in its own committed
        // write. If the worker is killed/times out between the Stripe charge and
        // the addon commit, the debounce already holds — so the next low-balance
        // turn will NOT charge the card again (turning a possible re-charge loop
        // into at most one orphaned charge, reconciled by the addon upsert below).
        $billing->update(['auto_recharge_last_triggered_at' => now()]);

        $invoice = $billing->invoicePrice($priceId, $units, [], [
            'metadata' => [
                'id' => 'auto_recharge_credits',
                'type' => 'addon',
                'quantity' => $units,
                'messages' => $unitMessages * $units,
            ],
        ]);

        $stripeInvoiceId = $invoice->asStripeInvoice()->id;

        return DB::transaction(function () use ($user, $units, $unitMessages, $stripeInvoiceId): UserAddon {
            // Idempotent on the Stripe invoice id so a re-run for the same charge
            // can never grant tokens twice.
            /** @var UserAddon $addon */
            $addon = $user->addons()->updateOrCreate(
                ['stripe_id' => $stripeInvoiceId],
                [
                    'type' => 'auto_recharge_credits',
                    'amount' => $unitMessages * $units,
                    'started_at' => now(),
                    'ends_at' => now()->addMonth(),
                ],
            );

            $user->refresh();
            $user->creditSystem()->refreshCanSendMessages();

            return $addon;
        });
    }
}
