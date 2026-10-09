<?php

namespace Domain\Integration\Connector\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Where a catalog connector stands for one agent, as shown on the agent's
 * Connectors page:
 *
 *   - Attached   → the workspace connected it and this agent uses it.
 *   - Unattached → the workspace connected it, but this agent does not use it.
 *   - Available  → the workspace has not connected it yet.
 */
#[TypeScript]
enum ConnectorRowState: string
{
    case Attached = 'attached';
    case Unattached = 'unattached';
    case Available = 'available';
}
