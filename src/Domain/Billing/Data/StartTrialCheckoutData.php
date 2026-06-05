<?php

namespace Domain\Billing\Data;

use Spatie\LaravelData\Data;

/**
 * Inputs for the public trial-signup funnel. Carries the visitor's IP so the
 * Stripe-side metadata can be matched back to a registered user later.
 */
class StartTrialCheckoutData extends Data
{
    public function __construct(
        public string $package,
        public bool $yearly,
        public string $userIp,
    ) {}
}
