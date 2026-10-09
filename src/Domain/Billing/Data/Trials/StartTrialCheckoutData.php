<?php

namespace Domain\Billing\Data\Trials;

use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Spatie\LaravelData\Data;

/**
 * Inputs for the public "start a trial" checkout. Carries the visitor's IP so
 * the account the webhook creates records where it signed up from.
 */
class StartTrialCheckoutData extends Data
{
    public function __construct(
        public string $package,
        public bool $yearly,
        public string $userIp,
        public CheckoutUrlsData $urls,
    ) {}
}
