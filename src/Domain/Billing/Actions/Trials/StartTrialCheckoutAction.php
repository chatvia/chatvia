<?php

namespace Domain\Billing\Actions\Trials;

use Domain\Billing\Actions\Webhooks\ProvisionTrialWorkspaceAction;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Trials\StartTrialCheckoutData;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\RegistrationType;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * Creates a Stripe Checkout Session for the public "start a trial" funnel and
 * returns it, so the caller can redirect to its URL. No account exists yet:
 * the webhook creates it when the checkout completes
 * ({@see ProvisionTrialWorkspaceAction}).
 *
 * The same metadata goes on the session (read by `checkout.session.completed`)
 * and on `subscription_data` (Stripe copies only that onto the subscription),
 * built once so the two cannot drift.
 *
 * @throws InvalidArgumentException when the package is unknown, archived or sold through sales only
 */
class StartTrialCheckoutAction
{
    public function __construct(
        private readonly BillingCatalog $catalog,
    ) {}

    public function execute(StartTrialCheckoutData $data): StripeCheckoutSession
    {
        $plan = $this->catalog->plan($data->package);

        // An archived plan is no longer offered to new customers.
        if (! $plan instanceof BillingPlan || ! $plan->is_active) {
            throw new InvalidArgumentException("Unknown billing package: {$data->package}");
        }

        // Contact-sales packages (Enterprise) have no Stripe price.
        if ($plan->contact_sales) {
            throw new InvalidArgumentException("Package is not self-serve: {$data->package}");
        }

        $package = strtolower($data->package);
        $trialDays = (int) config('billing.trial.days', 7);

        $metadata = [
            StripeMetadata::REGISTRATION_TYPE => RegistrationType::TrialRegistration->value,
            StripeMetadata::PACKAGE => $package,
            StripeMetadata::IS_YEARLY => StripeMetadata::flag($data->yearly),
            StripeMetadata::USER_IP => $data->userIp,
        ];

        return Cashier::stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [
                ['price' => $plan->stripePriceId(BillingInterval::fromYearly($data->yearly)), 'quantity' => 1],
            ],
            'success_url' => $data->urls->successUrl,
            'cancel_url' => $data->urls->cancelUrl,
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
            'subscription_data' => [
                'trial_period_days' => $trialDays,
                'metadata' => [
                    ...$metadata,
                    // Fallback labels for renewal invoices: the renewal derives
                    // the plan from the subscription's Stripe price and reads
                    // these only when no plan has that price.
                    StripeMetadata::TO_PACKAGE => $package,
                    StripeMetadata::FROM_PACKAGE => $this->catalog->defaultPlanKey(),
                    'trial_days' => (string) $trialDays,
                ],
            ],
            'metadata' => $metadata,
        ]);
    }
}
