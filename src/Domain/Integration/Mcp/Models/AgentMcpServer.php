<?php

namespace Domain\Integration\Mcp\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Enums\McpServerCategory;
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
 * @property int $id
 * @property string $external_id
 * @property int|null $user_id
 * @property McpServerCategory|null $category
 * @property string|null $connector_slug
 * @property bool $uses_central_catalog
 * @property string $name
 * @property string|null $description
 * @property string $slug
 * @property string $url
 * @property string $transport
 * @property string $auth_type
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
 * @property-read User $user
 * @property-read Collection<int, Agent> $agents
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AgentMcpServer attachedToAgent(\Domain\Agent\Models\Agent $agent)
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
class AgentMcpServer extends Model
{
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'category' => McpServerCategory::class,
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

        return $this->oauth_expires_at->lt(now()->addSeconds(30));
    }

    protected static function booted(): void
    {
        static::creating(function (self $server): void {
            if (! $server->slug && $server->name) {
                $server->slug = self::makeSlug($server->name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * (kept in sync by McpCatalog::refresh()) so the PHP branch points and the
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
     * Whether a server with this slug + URL may share the central catalog:
     * only when the URL is the connector's CANONICAL one. A preset whose URL
     * is workspace-supplied (requires_url / BYO-OAuth, e.g. Zendesk or
     * Salesforce) is tenant-specific — its tool definitions must stay
     * per-server so one workspace can never write tool text another
     * workspace's agents read.
     */
    public static function shouldUseCentralCatalog(?string $connectorSlug, ?string $url): bool
    {
        if ($connectorSlug === null) {
            return false;
        }

        $canonical = self::canonicalConnectorUrl($connectorSlug);

        return $canonical !== null
            && rtrim((string) $url, '/') === rtrim($canonical, '/');
    }

    /**
     * The connector's canonical MCP URL: the preset's configured `mcp_url`
     * (config/connectors.php), or `{app.url}/mcp/{slug}` for builtins
     * (config/mcp.php). Null when the catalog defines no fixed endpoint —
     * i.e. the workspace supplies the URL at connect time.
     */
    public static function canonicalConnectorUrl(string $connectorSlug): ?string
    {
        $preset = app(ConnectorCatalog::class)->find($connectorSlug);
        if ($preset !== null) {
            return $preset->mcpUrl !== '' ? $preset->mcpUrl : null;
        }

        foreach ((array) config('mcp.builtin', []) as $entry) {
            if (($entry['slug'] ?? null) === $connectorSlug) {
                return rtrim((string) config('app.url'), '/').'/mcp/'.$connectorSlug;
            }
        }

        return null;
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
            ->where('user_id', $agent->user_id)
            ->whereExists(function ($q) use ($agent): void {
                $q->from('agent_mcp_server')
                    ->whereColumn('agent_mcp_server.mcp_server_id', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agent->id);
            });
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
        $query->where('user_id', $agent->user_id);
    }

    public static function makeSlug(string $input): string
    {
        $slug = preg_replace('/[^a-z0-9_]+/', '_', strtolower($input)) ?? 'mcp';
        $slug = trim($slug, '_');

        return $slug === '' ? 'mcp' : substr($slug, 0, 40);
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
