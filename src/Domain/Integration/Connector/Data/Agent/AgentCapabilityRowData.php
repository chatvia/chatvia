<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Agent;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the agent's Capabilities page — a first-party capability preset
 * plus whether it is enabled for this agent. Capabilities are no-auth, so
 * unlike connectors there is no "available / connect first" intermediate
 * state: the toggle provisions and attaches in one step.
 */
#[TypeScript]
class AgentCapabilityRowData extends Data
{
    public function __construct(
        public ConnectorData $connector,
        public bool $enabled,
    ) {}
}
