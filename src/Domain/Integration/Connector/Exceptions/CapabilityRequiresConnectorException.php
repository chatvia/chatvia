<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Exceptions;

use RuntimeException;

/**
 * A capability that works through other connectors (Calendar through
 * Calendly or Cal.com) was turned on before the workspace connected one.
 * {@see $connectorNames} reads "Calendly or Cal.com".
 */
class CapabilityRequiresConnectorException extends RuntimeException
{
    public function __construct(public readonly string $connectorNames)
    {
        parent::__construct("Connect {$connectorNames} first.");
    }
}
