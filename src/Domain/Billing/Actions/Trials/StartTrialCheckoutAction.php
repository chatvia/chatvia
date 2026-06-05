<?php

namespace Domain\Billing\Actions\Trials;

use Domain\Billing\Data\StartTrialCheckoutData;
use InvalidArgumentException;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session as StripeCheckoutSession;

/**
 * Create a Stripe Checkout session for the public "Start trial" funnel.
 *
 * Returns the Stripe Checkout Session so the caller can redirect to its URL.
 * Throws InvalidArgumentException when the requested package is not configured —
 * the controller maps that to a redirect with a flash error.
 *
 * Note on metadata: the same payload is set on BOTH the top-level Checkout
 * session metadata AND the subscription_data.metadata. Stripe only forwards
 * subscription_data.metadata onto the actual Subscription object — the
 * top-level set is what `checkout.session.completed` webhook reads. We build
 * the array once and pass it to both fields so the contract can't drift.
 *
 * Consumer of this metadata: Domain\Billing\Listeners\HandleStripeWebhook
 * (registration_type, package, is_yearly keys on checkout.session.completed).
 */
class StartTrialCheckoutAction
{
    public function execute(StartTrialCheckoutData $data): StripeCheckoutSession
    {
        $plans = config('billing.packages');

        if (! isset($plans[$data->package])) {
            throw new InvalidArgumentException("Unknown billing package: {$data->package}");
        }

        $stripePriceId = $data->yearly
            ? $plans[$data->package]['stripe_yearly_price_id']
            : $plans[$data->package]['stripe_monthly_price_id'];

        $trialDays = (int) config('billing.trial.days', 7);

        $metadata = [
            'registration_type' => 'trial_registration',
            'package' => strtolower($data->package),
            'is_yearly' => $data->yearly ? 'true' : 'false',
            'user_ip' => $data->userIp,
        ];

        return Cashier::stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'line_items' => [
                ['price' => $stripePriceId, 'quantity' => 1],
            ],
            'success_url' => route('trial.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('home').'#pricing',
            'allow_promotion_codes' => true,
            'billing_address_collection' => 'required',
            'subscription_data' => [
                'trial_period_days' => $trialDays,
                'metadata' => array_merge($metadata, [
                    // These two are only consumed by downstream invoice events
                    // (HandleStripeWebhook checks `to_package`/`from_package`
                    // on subscription_cycle). Belong on the subscription, not
                    // the checkout session.
                    'to_package' => strtolower($data->package),
                    'from_package' => 'starter',
                    'trial_days' => (string) $trialDays,
                ]),
            ],
            'metadata' => $metadata,
        ]);
    }
}
