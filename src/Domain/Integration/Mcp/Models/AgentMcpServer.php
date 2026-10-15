<?php

namespace Domain\Integration\Mcp\Models;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Database\Factories\AgentMcpServerFactory;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Tool\Contracts\AttachableToAgents;
use Domain\Integration\Tool\Data\ToolName;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Support\Concerns\HasExternalId;

/**
 * An MCP server a workspace connected — a catalog connector (Notion, Stripe,
 * Charts, …) or a custom server added by URL. The row is owned by the
 * workspace (`workspace_id`) and agents opt in through the
 * `agent_mcp_server` pivot. Tool definitions live either in the shared
 * `connector_tools` catalog (canonical-URL connectors, see
 * `uses_central_catalog`) or in this server's own `mcp_tools` rows.
 *
 * The domain glossary calls this concept an "McpServer"; the class keeps its
 * historical name because it is referenced from persisted data.
 *
 * @property int $id
 * @property string $external_id
 * @property int|null $workspace_id
 * @property McpServerCategory|null $category
 * @property string|null $connector_slug
 * @property bool $uses_central_catalog
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property string $url
 * @property McpTransport $transport
 * @property McpAuthType $auth_type
 * @property string|null $auth_value
 * @property array<array-key, mixed>|null $headers
 * @property int $timeout_ms
 * @property bool $is_enabled
 * @property Carbon|null $catalog_cached_at
 * @property int $catalog_tools_count
 * @property Carbon|null $last_invoked_at
 * @property string|null $last_error
 * @property int $invocation_count
 * @property string|null $oauth_authorization_server
 * @property string|null $oauth_token_endpoint
 * @property string|null $oauth_refresh_token
 * @property Carbon|null $oauth_expires_at
 * @property string|null $oauth_scopes
 * @property string|null $oauth_client_id
 * @property string|null $oauth_client_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace $workspace
 * @property-read Collection<int, Agent> $agents
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer attachedToAgent(\Domain\Agent\Models\Agent $agent)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer custom()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer ownedBySameAccountAs(\Domain\Agent\Models\Agent $agent)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereAuthValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereCatalogCachedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereCatalogToolsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereConnectorSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereHeaders($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereInvocationCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereLastInvokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereOauthAuthorizationServer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereOauthExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereOauthRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereOauthScopes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereOauthTokenEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereTimeoutMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereTransport($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer whereUrl($value)
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[Table(name: 'mcp_servers')]
#[RouteKey('external_id')]
class AgentMcpServer extends Model implements AttachableToAgents
{
    /** @use HasFactory<AgentMcpServerFactory> */
    use HasExternalId, HasFactory;

    /**
     * How long one MCP request may take before it times out. A visitor waits
     * for the reply while a tool runs, so a slow server must not hold the chat
     * (and a server worker) for minutes; the model hears the call failed.
     */
    public const int DEFAULT_TIMEOUT_MS = 60_000;

    /** How early before expiry an OAuth access token counts as stale. */
    private const int TOKEN_EXPIRY_MARGIN_SECONDS = 30;

    protected function casts(): array
    {
        return [
            'category' => McpServerCategory::class,
            'transport' => McpTransport::class,
            'auth_type' => McpAuthType::class,
            'headers' => 'encrypted:array',
            'auth_value' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'is_enabled' => 'boolean',
            'uses_central_catalog' => 'boolean',
            'timeout_ms' => 'int',
            'catalog_tools_count' => 'int',
            'invocation_count' => 'int',
            'catalog_cached_at' => 'datetime',
            'last_invoked_at' => 'datetime',
            'oauth_expires_at' => 'datetime',
        ];
    }

    public function isOAuth(): bool
    {
        return $this->oauth_token_endpoint !== null;
    }

    /**
     * Returns true if the stored access token has expired (or is about to —
     * we give ourselves a 30s window to avoid mid-flight expiry).
     */
    public function accessTokenIsStale(): bool
    {
        if (! $this->isOAuth() || ! $this->oauth_expires_at) {
            return false;
        }

        return $this->oauth_expires_at->lt(now()->addSeconds(self::TOKEN_EXPIRY_MARGIN_SECONDS));
    }

    protected static function booted(): void
    {
        static::creating(function (self $server): void {
            if (! $server->slug && $server->name) {
                $server->slug = self::makeSlug($server->name);
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_mcp_server', 'mcp_server_id', 'agent_id');
    }

    /**
     * Tools discovered on this server (via the most recent catalog sync).
     * Populated only when `uses_central_catalog` is false (custom servers and
     * presets with a workspace-supplied URL); canonical-URL preset/builtin
     * servers route their tools through the shared connector_tools catalog +
     * the connectorTools() pivot instead.
     *
     * @return HasMany<McpTool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(McpTool::class, 'mcp_server_id');
    }

    /**
     * Catalog tools this server advertises, drawn from the shared
     * connector_tools table through the mcp_server_tool pivot — only for
     * servers with `uses_central_catalog` true. The pivot carries this
     * workspace's per-tool `permission` and invocation counters.
     *
     * @return BelongsToMany<ConnectorTool, $this, McpServerTool>
     */
    public function connectorTools(): BelongsToMany
    {
        return $this->belongsToMany(ConnectorTool::class, 'mcp_server_tool', 'mcp_server_id', 'connector_tool_id')
            ->using(McpServerTool::class)
            ->withPivot('permission', 'invocation_count', 'last_invoked_at');
    }

    /**
     * True when this server's tools live in the shared connector_tools catalog
     * rather than its own per-server mcp_tools rows. Reads the persisted column
     * (kept in sync by SyncMcpServerCatalogAction) so the PHP branch points and the
     * raw-SQL read paths (ToolRegistry, ToolHybridSearch) can never disagree
     * about a server's storage shape.
     */
    public function usesCentralCatalog(): bool
    {
        // (bool) — a freshly create()d instance hasn't loaded the column's DB
        // default, so the attribute can be null in memory.
        return (bool) $this->uses_central_catalog;
    }

    /**
     * Resolve an MCP server in the user's workspace by slug, or 404. Servers
     * belong to the workspace owner, so a teammate resolves the same rows.
     */
    public static function forSlugScoped(string $slug, User $user): self
    {
        return $user->workspace()->mcpServers()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Scope to servers in the agent owner's account that this agent has on
     * its `agent_mcp_server` pivot — used by the agent-side connector
     * controllers to resolve a row.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function attachedToAgent(Builder $query, Agent $agent): void
    {
        $query
            ->where('workspace_id', $agent->workspace_id)
            ->whereHas('agents', fn (Builder $agents): Builder => $agents->whereKey($agent->id));
    }

    /**
     * Scope to all servers in the agent owner's account (regardless of whether
     * this specific agent has them attached).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ownedBySameAccountAs(Builder $query, Agent $agent): void
    {
        $query->where('workspace_id', $agent->workspace_id);
    }

    /**
     * Servers the workspace added by URL — no catalog entry behind them.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function custom(Builder $query): void
    {
        $query->whereNull('connector_slug');
    }

    /**
     * Whether the workspace added this server by URL (see {@see self::custom()}).
     */
    public function isCustom(): bool
    {
        return $this->connector_slug === null;
    }

    /**
     * A slug-safe form of a name: lowercase letters, digits and underscores,
     * at most 40 characters (the column width).
     */
    public static function makeSlug(string $input): string
    {
        $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($input)) ?? 'mcp';
        $slug = trim($slug, '_');

        return $slug === '' ? 'mcp' : substr($slug, 0, 40);
    }

    /**
     * A slug for a new server that no other server of this workspace uses
     * yet: the name's slug, or the slug with `_2`, `_3`, … appended. Slugs are
     * unique per workspace, and the slug is the routing prefix of every tool
     * name, so a clash would mix two servers' tools.
     */
    public static function uniqueSlugFor(Workspace $owner, string $name): string
    {
        $base = self::makeSlug($name);
        $slug = $base;

        for ($suffix = 2; $owner->mcpServers()->where('slug', $slug)->exists(); $suffix++) {
            $slug = substr($base, 0, 38).'_'.$suffix;
        }

        return $slug;
    }

    /**
     * Record a successful catalog sync: when it ran, how many tools the server
     * advertises, and that the last error (if any) is resolved.
     */
    public function markCatalogSynced(int $toolsCount): void
    {
        $this->forceFill([
            'catalog_cached_at' => now(),
            'catalog_tools_count' => $toolsCount,
            'last_error' => null,
        ])->save();
    }

    /**
     * Keep a failed connect / refresh visible on the row so the dashboard can
     * show the workspace what went wrong.
     */
    public function recordError(string $message): void
    {
        $this->forceFill(['last_error' => $message])->save();
    }

    /**
     * Flip the workspace-wide on/off switch. A disabled server keeps its
     * attachments but contributes no tools to any agent.
     */
    public function toggleEnabled(): void
    {
        $this->forceFill(['is_enabled' => ! $this->is_enabled])->save();
    }

    /**
     * The prefix the LLM sees for tools coming from this server:
     * `mcp__{slug}__{tool}` routes to this server (see {@see ToolName}).
     */
    public function toolPrefix(): string
    {
        return ToolName::mcpPrefix((string) $this->slug);
    }
}
