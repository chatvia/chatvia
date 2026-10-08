<?php

namespace Domain\Billing\Exceptions;

use RuntimeException;

/**
 * Thrown when an add-on is bought but its Stripe price is not set in the
 * environment. The message is safe to show to the user.
 */
class AddonNotConfiguredException extends RuntimeException {}
