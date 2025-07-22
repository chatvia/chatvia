<?php

namespace App\Http\Controllers\Billing\Payments;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingPaymentMethodsController
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('billing/PaymentMethods', [
            'paymentMethods' => $request->user()->paymentMethods()->map(function ($paymentMethod) use ($user) {
                return [
                    'id' => $paymentMethod->id,
                    'type' => $paymentMethod->type,
                    'billing_details' => $paymentMethod->billing_details,
                    'card' => $paymentMethod->card ?? null,
                    'is_default' => $paymentMethod->id === $user->defaultPaymentMethod()?->id,
                ];
            }),
            'intent' => $request->user()->createSetupIntent(),
            'stripeKey' => config('cashier.key'),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'billing_address' => $user->billing_address ?? null,
            ],
        ]);
    }
}
