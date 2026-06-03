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

        $invoice = $billing->invoicePrice($priceId, $units, [], [
            'metadata' => [
                'id' => 'auto_recharge_credits',
                'type' => 'addon',
                'quantity' => $units,
                'messages' => $unitMessages * $units,
            ],
        ]);

        $stripeInvoiceId = $invoice->asStripeInvoice()->id;

        return DB::transaction(function () use ($user, $units, $unitMessages, $stripeInvoiceId, $billing): UserAddon {
            /** @var UserAddon $addon */
            $addon = $user->addons()->create([
                'type' => 'auto_recharge_credits',
                'amount' => $unitMessages * $units,
                'stripe_id' => $stripeInvoiceId,
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);

            $billing->update(['auto_recharge_last_triggered_at' => now()]);

            $user->refresh();
            $user->creditSystem()->refreshCanSendMessages();

            return $addon;
        });
    }
}
