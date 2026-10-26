<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * The connector picked for a capability is not one it works through, or the
 * workspace has not connected it.
 */
class CapabilityProviderUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Connect this service before choosing it.');
    }
}
