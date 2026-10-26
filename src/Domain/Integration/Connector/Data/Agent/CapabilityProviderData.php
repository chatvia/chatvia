<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Agent;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One connector a capability can work through, on the Capabilities page:
 * the catalog entry and whether the workspace has it connected.
 */
#[TypeScript]
class CapabilityProviderData extends Data
{
    public function __construct(
        public ConnectorData $connector,
        public bool $connected,
    ) {}
}
