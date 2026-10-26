<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * A capability the install does not offer right now: its service is not set
 * up in the admin panel (Image generation without an image service), or the
 * install has no license key, so an agent cannot turn it on.
 */
class CapabilityUnavailableException extends RuntimeException
{
    public function __construct(string $capability)
    {
        parent::__construct("{$capability} is turned off for this server.");
    }
}
