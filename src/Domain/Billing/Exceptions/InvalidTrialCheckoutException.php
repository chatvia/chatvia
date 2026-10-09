<?php

namespace Domain\Billing\Exceptions;

use RuntimeException;

/**
 * The checkout session on the trial success page is not a finished, recent
 * Stripe checkout, so nothing may be done with it.
 */
class InvalidTrialCheckoutException extends RuntimeException {}
