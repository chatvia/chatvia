<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;

class TrialCheckoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'package' => ['required', 'string', 'in:starter,standard,pro'],
            'yearly' => ['required', 'boolean'],
        ]);

        $package = $request->input('package');
        $yearly = (bool) $request->input('yearly');

        $plans = config('billing.packages');

        if (! isset($plans[$package])) {
            return to_route('home')->with('error', 'Invalid package selected.');
        }

        $stripePriceId = $yearly
            ? $plans[$package]['stripe_yearly_price_id']
            : $plans[$package]['stripe_monthly_price_id'];

        $trialDays = (int) config('billing.trial.days', 7);

        try {
            // Capture user's IP address
            $userIp = $request->ip();

            // Create checkout session using Cashier's Stripe client
            $checkoutSession = Cashier::stripe()->checkout->sessions->create([
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $stripePriceId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => route('trial.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('home').'#pricing',
                'allow_promotion_codes' => true,
                'billing_address_collection' => 'required',
                'subscription_data' => [
                    'trial_period_days' => $trialDays,
                    'metadata' => [
                        'registration_type' => 'trial_registration',
                        'package' => strtolower($package),
                        'is_yearly' => $yearly ? 'true' : 'false',
                        'to_package' => strtolower($package),
                        'from_package' => 'starter',
                        'trial_days' => (string) $trialDays,
                        'user_ip' => $userIp,
                    ],
                ],
                'metadata' => [
                    'registration_type' => 'trial_registration',
                    'package' => strtolower($package),
                    'is_yearly' => $yearly ? 'true' : 'false',
                    'user_ip' => $userIp,
                ],
            ]);

            return redirect($checkoutSession->url);
        } catch (Exception $e) {
            report($e);

            return to_route('home')->with('error', 'Unable to create checkout session. Please try again.');
        }
    }
}
