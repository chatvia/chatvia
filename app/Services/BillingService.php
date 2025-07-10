<?php

namespace App\Services;

use App\Models\User;

class BillingService
{
    /**
     * Get all available packages
     */

    /**
     * Get a specific package by ID
     */
    public function getPackage(string $packageId): ?array
    {
        $package = config("billing.packages.{$packageId}");

        if (! $package) {
            return null;
        }

        return array_merge($package, [
            'id' => $packageId,
            'monthly_price_formatted' => $this->formatPrice($package['monthly_price']),
            'yearly_price_formatted' => $this->formatPrice($package['yearly_price']),
            'monthly_savings' => $this->calculateSavings($package['monthly_price'], $package['yearly_price']),
        ]);
    }

    /**
     * Subscribe a user to a package using Stripe Checkout
     */
    public function subscribe(User $user, string $packageId, string $billingCycle = 'monthly', ?string $paymentMethodId = null): array
    {
        $package = $this->getPackage($packageId);

        if (! $package) {
            return ['success' => false, 'message' => 'Invalid package selected'];
        }

        // Handle free package - no Stripe needed
        if ($packageId === 'free') {
            // Cancel any existing subscription
            if ($user->subscribed('default')) {
                $user->subscription('default')->cancelNow();
            }

            // Update user's package
            $user->updatePackage('free');

            return [
                'success' => true,
                'message' => 'Successfully downgraded to free plan',
            ];
        }

        // For paid packages, create Stripe Checkout Session
        try {
            // Get the Stripe price ID
            $priceId = $billingCycle === 'yearly'
                ? $package['stripe_yearly_price_id']
                : $package['stripe_monthly_price_id'];

            if (! $priceId) {
                return [
                    'success' => false,
                    'message' => "Price ID not configured for {$packageId} {$billingCycle}. Please set up your Stripe products first.",
                ];
            }

            // Create Stripe customer if needed
            if (! $user->hasStripeId()) {
                $user->createAsStripeCustomer([
                    'name' => $user->name,
                    'email' => $user->email,
                ]);
            }

            // Create checkout session
            $stripe = new \Stripe\StripeClient(config('cashier.secret'));

            $checkoutSession = $stripe->checkout->sessions->create([
                'customer' => $user->stripe_id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.packages').'?canceled=1',
                'metadata' => [
                    'package_id' => $packageId,
                    'billing_cycle' => $billingCycle,
                    'user_id' => $user->id,
                ],
            ]);

            return [
                'success' => true,
                'message' => 'Redirecting to Stripe Checkout...',
                'checkout_url' => $checkoutSession->url,
            ];

        } catch (\Exception $exception) {
            \Log::error('Stripe Checkout creation failed', [
                'user_id' => $user->id,
                'package' => $packageId,
                'billing_cycle' => $billingCycle,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create checkout session: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Cancel a user's subscription
     */
    public function cancelSubscription(User $user, bool $immediately = false): array
    {
        if (! $user->subscribed('default')) {
            return ['success' => false, 'message' => 'No active subscription found'];
        }

        try {
            $subscription = $user->subscription('default');

            if ($immediately) {
                $subscription->cancelNow();
                $user->updatePackage('free');
                $message = 'Subscription cancelled immediately';
            } else {
                $subscription->cancel();
                $message = 'Subscription will be cancelled at the end of the current period';
            }

            return ['success' => true, 'message' => $message];

        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to cancel subscription: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Resume a cancelled subscription
     */
    public function resumeSubscription(User $user): array
    {
        if (! $user->subscribed('default')) {
            return ['success' => false, 'message' => 'No subscription found'];
        }

        try {
            $subscription = $user->subscription('default');

            if (! $subscription->cancelled()) {
                return ['success' => false, 'message' => 'Subscription is not cancelled'];
            }

            $subscription->resume();

            return ['success' => true, 'message' => 'Subscription resumed successfully'];

        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to resume subscription: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Update payment method for a user's subscription
     */
    public function updatePaymentMethod(User $user, string $paymentMethodId): array
    {
        try {
            $user->updateDefaultPaymentMethod($paymentMethodId);

            return ['success' => true, 'message' => 'Payment method updated successfully'];

        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => 'Failed to update payment method: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Start a trial for a user
     */

    /**
     * Get user's billing information
     */
    public function getBillingInfo(User $user): array
    {
        $currentPackage = $this->getPackage($user->getCurrentPackage());
        $subscription = $user->subscribed('default') ? $user->subscription('default') : null;

        return [
            'current_package' => $currentPackage,
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'current_period_end' => $subscription->asStripeSubscription()->current_period_end,
                'cancel_at_period_end' => $subscription->cancelled(),
                'trial_end' => $subscription->trial_end,
            ] : null,
            'on_trial' => $user->onTrial(),
            'trial_ends_at' => $user->trial_ends_at,
            'has_payment_method' => $user->hasDefaultPaymentMethod(),
            'payment_methods' => $user->paymentMethods(),
        ];
    }

    /**
     * Format price for display
     */
    private function formatPrice(int $priceInCents): string
    {
        $symbol = config('billing.currency_symbol', '$');
        $price = $priceInCents / 100;

        return $symbol.number_format($price, 2);
    }

    /**
     * Calculate yearly savings
     */
    private function calculateSavings(int $monthlyPrice, int $yearlyPrice): array
    {
        if ($monthlyPrice === 0 || $yearlyPrice === 0) {
            return ['amount' => 0, 'percentage' => 0, 'formatted' => '$0'];
        }

        $yearlyEquivalent = $monthlyPrice * 12;
        $savings = $yearlyEquivalent - $yearlyPrice;
        $percentage = round(($savings / $yearlyEquivalent) * 100);

        return [
            'amount' => $savings,
            'percentage' => $percentage,
            'formatted' => $this->formatPrice($savings),
        ];
    }

    /**
     * Check if user should receive a trial
     */
    private function shouldReceiveTrial(User $user, string $packageId): bool
    {
        $trialPackage = config('billing.trial.package');

        // Only offer trial for the designated trial package
        if ($packageId !== $trialPackage) {
            return false;
        }

        // Check if user has never had a trial
        return ! $user->hasEverSubscribed() && $user->trial_ends_at === null;
    }
}
