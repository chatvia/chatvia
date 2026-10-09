<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Stripe;

use Spatie\LaravelData\Data;

/**
 * Where Stripe Checkout sends the customer back to. Built by the HTTP layer
 * (it owns the routes) and passed into the checkout Actions, so the domain
 * never generates URLs itself.
 */
class CheckoutUrlsData extends Data
{
    public function __construct(
        public string $successUrl,
        public string $cancelUrl,
    ) {}
}
