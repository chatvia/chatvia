<?php

namespace Domain\Billing\Actions\Webhooks;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Data\Stripe\PaidInvoiceData;
use Domain\Billing\Data\Subscriptions\StripeScheduleData;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Services\RenewedPlanResolver;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

/**
 * Handles a paid subscription invoice (`invoice.payment_succeeded`). On a
 * renewal it grants the plan the invoice paid for — a new credit cycle and the
 * plan's quotas — ends a trial that is still running, and clears a scheduled
 * plan change that has now taken effect.
 *
 * The first invoice of a subscription is skipped: the trial checkout already
 * granted the plan. A renewal of an add-on subscription is skipped too, so it
 * never resets the cycle's message credits or overwrites the plan quotas.
 */
class GrantRenewalAction
{
    public function __construct(
        private readonly RenewedPlanResolver $plans,
        private readonly GrantSubscriptionAction $grantSubscription,
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(PaidInvoiceData $invoice): void
    {
        if ($invoice->opensSubscription()) {
            Log::info('Skipping trial invoice - already handled via checkout.session.completed', [
                'invoice_id' => $invoice->invoiceId,
                'customer' => $invoice->customerId,
            ]);

            return;
        }

        $billing = UserBilling::forStripeCustomer($invoice->customerId);
        $user = $billing?->user;

        if ($billing === null || $user === null) {
            Log::warning('User not found for invoice payment', ['customer' => $invoice->customerId]);

            return;
        }

        if ($invoice->renewsSubscription()) {
            $this->renew($invoice, $billing, $user);
        }
    }

    private function renew(PaidInvoiceData $invoice, UserBilling $billing, User $user): void
    {
        $subscription = $billing->ownedSubscription('default');
        $plan = $this->plans->resolve($invoice, $billing, $subscription);

        if ($plan === null) {
            Log::info('Skipping renewal invoice that is not for the plan subscription', [
                'invoice_id' => $invoice->invoiceId,
                'customer' => $invoice->customerId,
            ]);

            return;
        }

        $this->endTrial($subscription, $user);

        $this->grantSubscription->execute($user, $plan);

        $this->clearAppliedPlanChange($invoice, $subscription);

        $user->refresh();
        $this->creditSystem->forWorkspace($user->workspace()->id)->refreshCanSendMessages();
    }

    /**
     * The first paid invoice moves a subscription that is still trialing to
     * active.
     */
    private function endTrial(?Subscription $subscription, User $user): void
    {
        if ($subscription === null || ! $subscription->onTrial()) {
            return;
        }

        $subscription->endTrial();

        Log::info('Trial ended after first payment', [
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
    }

    private function clearAppliedPlanChange(PaidInvoiceData $invoice, ?Subscription $subscription): void
    {
        if ($subscription === null || ! $invoice->subscriptionMetadata?->hasPendingChange()) {
            return;
        }

        $subscription->updateStripeSubscription([
            'metadata' => StripeScheduleData::appliedMetadata(),
        ]);
    }
}
