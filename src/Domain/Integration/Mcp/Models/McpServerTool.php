<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Models;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot linking an {@see AgentMcpServer} to a shared catalog {@see ConnectorTool}:
 * which catalog tools the server advertises, plus that workspace's
 * user-toggleable permission and live invocation counters. Deliberately thin —
 * the heavy definition/embedding lives on the connector_tools row.
 *
 * @property int $mcp_server_id
 * @property int $connector_tool_id
 * @property McpToolPermission $permission
 * @property int $invocation_count
 * @property Carbon|null $last_invoked_at
 *
 * @mixin \Eloquent
 */
#[Table(name: 'connector_tool_mcp_server')]
#[WithoutTimestamps]
class McpServerTool extends Pivot
{
    protected function casts(): array
    {
        return [
            'permission' => McpToolPermission::class,
            'invocation_count' => 'int',
            'last_invoked_at' => 'datetime',
        ];
    }
}
