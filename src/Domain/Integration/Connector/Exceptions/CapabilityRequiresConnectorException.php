<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * A capability that works through another connector (Calendar through
 * Calendly) was turned on before the workspace connected that connector.
 */
class CapabilityRequiresConnectorException extends RuntimeException
{
    public function __construct(public readonly string $connectorName)
    {
        parent::__construct("Connect {$connectorName} first.");
    }
}
