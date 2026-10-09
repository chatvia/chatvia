<?php

namespace Domain\Integration\Tool\Enums;

use Domain\Integration\Tool\Search\ToolHybridSearch;

/**
 * The table a hybrid-search hit came from. Each pool has its own dense and BM25
 * channel in {@see ToolHybridSearch}; the values are the literal `kind` column
 * the search SQL emits.
 */
enum ToolSearchPool: string
{
    /** The shared `connector_tools` catalog (preset and builtin connectors). */
    case McpPreset = 'mcp_preset';

    /** A custom server's own `mcp_tools` rows. */
    case McpCustom = 'mcp_custom';

    /** Webhook `functions`. */
    case Function = 'fn';
}
