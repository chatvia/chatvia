<?php

namespace Domain\Integration\Mcp\Models;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\Vector;

/**
 * One row per tool discovered on an MCP server. Tools are first-class so
 * we can attach embeddings, expose them as FKs, set per-tool permission,
 * and query them directly.
 *
 * `name` is the raw MCP tool name (unprefixed). The `mcp__{slug}__` prefix
 * is reapplied at compile time so stored selections survive slug renames.
 *
 * `permission` is the tri-state shown in the UI — `Blocked` hides the tool
 * from the catalog, `Approval` routes through ToolApprovalCheckpoint,
 * `Allow` runs inline.
 *
 * @property int $id
 * @property int $mcp_server_id
 * @property string $name
 * @property string $description
 * @property array<array-key, mixed> $input_schema
 * @property array<array-key, mixed>|null $annotations
 * @property McpToolScope $scope
 * @property McpToolPermission $permission
 * @property Vector|null $embedding
 * @property string|null $embedding_bm25
 * @property Carbon $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AgentMcpServer $mcpServer
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class McpTool extends Model
{
    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'annotations' => 'array',
            'scope' => McpToolScope::class,
            'permission' => McpToolPermission::class,
            'embedding' => Vector::class,
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AgentMcpServer, $this>
     */
    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(AgentMcpServer::class, 'mcp_server_id');
    }
}
