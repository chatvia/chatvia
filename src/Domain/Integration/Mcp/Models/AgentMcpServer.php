<?php

namespace Domain\Integration\Mcp\Models;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Attributes\Guarded;
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
 * @property int $user_id
 * @property string|null $connector_slug
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
class AgentMcpServer extends Model
{
    use HasExternalId, HasFactory;

    protected function casts(): array
    {
        return [
            'headers' => 'encrypted:array',
            'auth_value' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
            'is_enabled' => 'boolean',
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
     *
     * @return HasMany<McpTool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(McpTool::class, 'mcp_server_id');
    }

    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    /**
     * Resolve a workspace-scoped MCP server by slug or 404 if the slug is
     * unknown to that workspace. Replaces the per-controller `find()` helper
     * that was copy-pasted across the Settings/Connector controllers.
     */
    public static function forSlugScoped(string $slug, User $user): self
    {
        return $user->mcpServers()->where('slug', $slug)->firstOrFail();
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
     * The prefix the LLM sees for tools coming from this server. Used as the
     * routing key by ToolRouter: `mcp__{slug}__{tool}` → this server.
     */
    public function toolPrefix(): string
    {
        return 'mcp__'.$this->slug.'__';
    }
}
