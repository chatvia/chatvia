<?php

namespace Domain\Integration\Mcp\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\Vector;

/**
 * One row per tool discovered on an MCP server. Tools are first-class so
 * we can attach embeddings, expose them as FKs, toggle per-tool enable
 * state, and query them directly.
 *
 * `name` is the raw MCP tool name (unprefixed). The `mcp__{slug}__` prefix
 * is reapplied at compile time so stored selections survive slug renames.
 *
 * `is_enabled` is scoped to the parent server — flipping it affects every
 * agent attached to that server.
 *
 * @property int $id
 * @property int $mcp_server_id
 * @property string $name
 * @property string $description
 * @property array<array-key, mixed> $input_schema
 * @property array<array-key, mixed>|null $annotations
 * @property bool $requires_approval
 * @property bool $is_enabled
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
    use HasFactory;

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'annotations' => 'array',
            'requires_approval' => 'boolean',
            'is_enabled' => 'boolean',
            'embedding' => Vector::class,
            'fetched_at' => 'datetime',
        ];
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(AgentMcpServer::class, 'mcp_server_id');
    }
}
