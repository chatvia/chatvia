<?php

namespace Domain\Security\Models;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Org-wide security & compliance settings — one row per workspace owner. The
 * shared dependency for the Enterprise Security suite: IP allowlisting, geo
 * controls, configurable retention, SIEM webhooks, and the SCIM toggle all
 * read from here. Resolve through {@see User::securityPolicy()} (memoized) or
 * {@see self::forWorkspace()} (firstOrCreate) so no caller ever hits null.
 *
 * The retention windows are in days; null means the install default (or, for
 * leads, analytics and anonymize, never). Daily jobs enforce them: the
 * conversation, audit-log, lead and analytics prunes, and the session
 * anonymizer.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $retention_conversations_days
 * @property int|null $retention_leads_days
 * @property int|null $retention_audit_days
 * @property int|null $retention_analytics_days
 * @property int|null $anonymize_after_days
 * @property IpAllowlistMode $ip_allowlist_mode
 * @property array<int, string>|null $ip_allowlist
 * @property GeoMode $geo_mode
 * @property array<int, string>|null $geo_countries
 * @property string|null $webhook_url
 * @property string|null $webhook_secret
 * @property bool $webhook_enabled
 * @property array<int, string>|null $webhook_events
 * @property int $webhook_failures
 * @property Carbon|null $webhook_last_delivered_at
 * @property bool $scim_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workspace $workspace
 */
#[Guarded(['id'])]
class WorkspaceSecurityPolicy extends Model
{
    /**
     * The network rules are read on every authenticated web request (see
     * EnsureWorkspaceNetworkAllowed), so they are cached. Any save or delete
     * clears the cache, so a policy change applies on the next request.
     */
    protected static function booted(): void
    {
        $forget = fn (self $policy): bool => Cache::forget(self::networkRulesCacheKey($policy->workspace_id));

        static::saved($forget);
        static::deleted($forget);
    }

    public static function networkRulesCacheKey(int $workspaceId): string
    {
        return 'security:network-rules:'.$workspaceId;
    }

    protected function casts(): array
    {
        return [
            'retention_conversations_days' => 'integer',
            'retention_leads_days' => 'integer',
            'retention_audit_days' => 'integer',
            'retention_analytics_days' => 'integer',
            'anonymize_after_days' => 'integer',
            'ip_allowlist_mode' => IpAllowlistMode::class,
            'ip_allowlist' => 'array',
            'geo_mode' => GeoMode::class,
            'geo_countries' => 'array',
            'webhook_secret' => 'encrypted',
            'webhook_enabled' => 'boolean',
            'webhook_events' => 'array',
            'webhook_failures' => 'integer',
            'webhook_last_delivered_at' => 'datetime',
            'scim_enabled' => 'boolean',
        ];
    }

    /**
     * The single resolution path — guarantees a row exists for the workspace so
     * every middleware/command/Action reads a concrete policy, never null.
     */
    public static function forWorkspace(int $workspaceId): self
    {
        return self::firstOrCreate(['workspace_id' => $workspaceId]);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
