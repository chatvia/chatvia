<?php

namespace Domain\Billing\Actions\AutoRecharge;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\BuyExtraMessageCreditsAction;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Support\Facades\DB;

/**
 * Charges the workspace's default payment method off-session for a number of
 * auto-recharge credit units and records the matching `user_addons` row.
 *
 * Unlike {@see BuyExtraMessageCreditsAction}, which opens a hosted Stripe
 * Checkout, this runs from a queued job where there is no browser to redirect,
 * so Cashier invoices and pays the price directly. Returns null when the
 * recharge price is not configured.
 */
class AutoRechargeCreditsAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(User $user, int $units): ?UserAddon
    {
        $pack = MessageCreditAddon::AutoRechargeCredits;
        $priceId = $pack->stripePriceId();

        if ($priceId === null) {
            return null;
        }

        $billing = $user->accountBillingOrFail();
        $messages = $pack->unitMessages() * $units;

        // Reserve the one-hour debounce BEFORE charging, in its own committed
        // write. If the worker dies between the Stripe charge and the add-on
        // commit, the debounce already holds, so the next low-balance turn does
        // not charge the card again: at most one charge is left without its
        // add-on, and the upsert below reconciles it on a retry.
        $billing->update(['auto_recharge_last_triggered_at' => now()]);

        $invoice = $billing->invoicePrice($priceId, $units, [], [
            'metadata' => [
                StripeMetadata::ID => $pack->value,
                StripeMetadata::TYPE => PurchaseType::Addon->value,
                StripeMetadata::QUANTITY => $units,
                StripeMetadata::MESSAGES => $messages,
            ],
        ]);

        $stripeInvoiceId = $invoice->asStripeInvoice()->id;

        // Keyed on the Stripe invoice id, so a re-run for the same charge can
        // never grant the credits twice.
        $addon = DB::transaction(fn (): UserAddon => $user->addons()->updateOrCreate(
            ['stripe_id' => $stripeInvoiceId],
            [
                'type' => $pack->value,
                'amount' => $messages,
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
            ],
        ));

        $user->refresh();
        $this->creditSystem->forWorkspace($user->workspace()->id)->refreshCanSendMessages();

        return $addon;
    }
}
