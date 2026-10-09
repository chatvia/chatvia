<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\PaymentMethod;

/**
 * The payment methods page: saved cards, which one is the default, and a
 * Stripe SetupIntent for adding a new card.
 */
class BillingPaymentMethodController
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $billing = $user->accountBilling();

        return Inertia::render('billing/PaymentMethods', [
            'paymentMethods' => $billing->paymentMethods()->map(function (PaymentMethod $paymentMethod) use ($billing) {
                $stripe = $paymentMethod->asStripePaymentMethod();
                $default = $billing->defaultPaymentMethod();
                $defaultId = $default instanceof PaymentMethod
                    ? $default->asStripePaymentMethod()->id
                    : null;

                return [
                    'id' => $stripe->id,
                    'type' => $stripe->type,
                    'billing_details' => $stripe->billing_details,
                    'card' => $stripe->card ?? null,
                    'is_default' => $stripe->id === $defaultId,
                ];
            }),
            'intent' => $billing->createSetupIntent(),
            'stripeKey' => config('cashier.key'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
