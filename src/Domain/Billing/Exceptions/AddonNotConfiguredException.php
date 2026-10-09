<?php

namespace Domain\Billing\Exceptions;

use Domain\Billing\Enums\Addon;
use RuntimeException;

/**
 * Thrown when an add-on is bought but its Stripe price is not set in the
 * environment. The message is safe to show to the user.
 */
class AddonNotConfiguredException extends RuntimeException
{
    public static function for(Addon $addon): self
    {
        return new self(sprintf(
            '%s addon is not configured. Set %s in your environment.',
            $addon->label(),
            $addon->priceEnvironmentKey(),
        ));
    }
}
