<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Agent;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on the agent's Capabilities page — a first-party capability preset
 * plus whether it is enabled for this agent. Capabilities are no-auth: the
 * toggle provisions and attaches in one step. A capability that works through
 * other connectors (Calendar through Calendly or Cal.com) is not `ready` until
 * the workspace connects one; {@see $providers} lists them for the page's
 * "connect first" prompt and its "book through" choice, and {@see $provider}
 * is the one in use.
 */
#[TypeScript]
class AgentCapabilityRowData extends Data
{
    public function __construct(
        public ConnectorData $connector,
        public bool $enabled,
        public bool $ready = true,
        /** @var list<CapabilityProviderData> */
        public array $providers = [],
        public ?string $provider = null,
    ) {}
}
