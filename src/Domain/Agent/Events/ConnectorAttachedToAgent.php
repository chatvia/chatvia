<?php

declare(strict_types=1);

namespace Domain\Agent\Events;

use Domain\Integration\Connector\Listeners\EnableCapabilitiesThroughConnector;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A connector was newly attached to an agent (not a repeat attach). A
 * capability that works through it reacts: attaching Calendly or Cal.com
 * turns Calendar on ({@see EnableCapabilitiesThroughConnector}).
 */
final readonly class ConnectorAttachedToAgent
{
    use Dispatchable;

    public function __construct(
        public int $agentId,
        public int $mcpServerId,
    ) {}
}
