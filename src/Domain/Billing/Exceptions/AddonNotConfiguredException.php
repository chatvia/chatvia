<?php

namespace Domain\Billing\Exceptions;

use Domain\Billing\Enums\Addon;
use RuntimeException;

/**
 * Thrown when an add-on is bought but has no Stripe price (none is set in the
 * admin panel, or the add-on is not sold). The message is safe to show to the
 * user.
 */
class AddonNotConfiguredException extends RuntimeException
{
    public static function for(Addon $addon): self
    {
        return new self(sprintf(
            '%s addon is not configured. Set its Stripe price in the admin panel.',
            $addon->label(),
        ));
    }
}
