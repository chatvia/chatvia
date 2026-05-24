<?php

namespace Domain\Agents\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AgentMcpServer extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'headers' => 'encrypted:array',
            'auth_value' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'enabled' => 'boolean',
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
        static::creating(function (self $server) {
            if (! $server->external_id) {
                $server->external_id = (string) Str::uuid();
            }
            if (! $server->slug && $server->name) {
                $server->slug = self::makeSlug($server->name);
            }
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
