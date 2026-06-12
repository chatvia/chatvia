<?php

namespace Domain\Integration\Mcp\Models;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Pgvector\Laravel\Vector;

/**
 * One row per tool of a PRESET ("GA") connector, keyed by connector_slug — the
 * central, workspace-agnostic catalog. leads/stripe/github/… are stored here
 * ONCE regardless of how many workspaces connect them, so the heavy columns
 * (definition + embeddings) and their vchordrq/BM25 indexes are single-copy.
 *
 * `name` is the raw MCP tool name (unprefixed); the `mcp__{slug}__` prefix is
 * reapplied at compile time from the per-workspace server's slug.
 *
 * `default_permission` is the seed a freshly-connected workspace inherits — the
 * live, user-toggleable permission lives on the {@see McpServerTool} membership.
 *
 * Custom servers (connector_slug IS NULL) do NOT use this table — see {@see McpTool}.
 *
 * @property int $id
 * @property string $connector_slug
 * @property string $name
 * @property string $description
 * @property array<array-key, mixed> $input_schema
 * @property array<array-key, mixed>|null $annotations
 * @property McpToolScope $scope
 * @property McpToolPermission $default_permission
 * @property Vector|null $embedding
 * @property string|null $embedding_bm25
 * @property Carbon $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read McpServerTool $pivot
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
class ConnectorTool extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'annotations' => 'array',
            'scope' => McpToolScope::class,
            'default_permission' => McpToolPermission::class,
            'embedding' => Vector::class,
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * The per-workspace servers that advertise this catalog tool, through the
     * mcp_server_tool pivot (carrying each workspace's permission + counters).
     *
     * @return BelongsToMany<AgentMcpServer, $this, McpServerTool>
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(AgentMcpServer::class, 'mcp_server_tool', 'connector_tool_id', 'mcp_server_id')
            ->using(McpServerTool::class)
            ->withPivot('permission', 'invocation_count', 'last_invoked_at');
    }

    /**
     * Delete catalog rows no server advertises anymore — membership reconciles
     * drop the pivot rows but never the catalog row, so a tool the upstream
     * removed would otherwise sit in the shared embedding indexes forever.
     * The staleness window keeps a row safe while any sync still touches it
     * (every advertising sync bumps fetched_at) and shields a concurrent
     * first-connect, whose firstOrCreate refreshes fetched_at in the same
     * transaction as its membership insert.
     */
    public static function pruneOrphaned(string $connectorSlug, int $olderThanDays = 7): int
    {
        return static::query()
            ->where('connector_slug', $connectorSlug)
            ->whereDoesntHave('servers')
            ->where('fetched_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }
}
