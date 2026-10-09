<?php

namespace App\Http\Requests\Billing\Concerns;

use Domain\Billing\Data\Stripe\CheckoutUrlsData;

/**
 * Stripe Checkout return URLs for checkouts started from the billing page:
 * the payment-success page when it went through, the billing page when the
 * customer backed out.
 */
trait ReturnsToBillingPage
{
    protected function checkoutUrls(): CheckoutUrlsData
    {
        return new CheckoutUrlsData(
            successUrl: route('billing.success'),
            cancelUrl: route('billing.index'),
        );
    }
}
