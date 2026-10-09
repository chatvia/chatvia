<?php

namespace Domain\Integration\Connector\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which list a row on the Settings → Connectors page belongs to: a catalog
 * connector (Featured) or an MCP server the workspace added by URL (Custom).
 */
#[TypeScript]
enum ConnectorRowKind: string
{
    case Featured = 'featured';
    case Custom = 'custom';
}
