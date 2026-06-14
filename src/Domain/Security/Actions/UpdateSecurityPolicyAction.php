<?php

namespace Domain\Security\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\WorkspaceSecurityPolicyData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The single write path for the keystone {@see WorkspaceSecurityPolicy}. Guards
 * the SIEM webhook URL against SSRF, persists the change, and emits one audit
 * event per changed category (network / retention / other) so each surfaces
 * under its specific {@see SecurityAuditAction} with a before/after diff.
 *
 * The `webhook_secret` is write-only: a non-null value rotates it, null leaves
 * the stored secret untouched, and its value is NEVER placed in audit metadata.
 *
 * Request-free per the layering rules — the controller resolves the acting user
 * + live request IP into {@see SecurityActorData} and passes it in.
 */
class UpdateSecurityPolicyAction
{
    /** Loopback / private / link-local / reserved ranges blocked for webhook hosts. */
    private const BLOCKED_RANGES = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
        '169.254.0.0/16', '0.0.0.0/8', '100.64.0.0/10',
        '::1/128', 'fc00::/7', 'fe80::/10',
    ];

    /** @var array<int, string> */
    private const RETENTION_FIELDS = [
        'retention_conversations_days', 'retention_leads_days', 'retention_audit_days',
        'retention_analytics_days', 'anonymize_after_days',
    ];

    /** @var array<int, string> */
    private const NETWORK_FIELDS = ['ip_allowlist_mode', 'ip_allowlist', 'geo_mode', 'geo_countries'];

    public function execute(User $workspace, WorkspaceSecurityPolicyData $data, SecurityActorData $actor): WorkspaceSecurityPolicy
    {
        $this->guardWebhookUrl($data->webhook_url);

        $policy = WorkspaceSecurityPolicy::forWorkspace($workspace->id);
        $original = $policy->getOriginal();

        $attributes = [
            'retention_conversations_days' => $data->retention_conversations_days,
            'retention_leads_days' => $data->retention_leads_days,
            'retention_audit_days' => $data->retention_audit_days,
            'retention_analytics_days' => $data->retention_analytics_days,
            'anonymize_after_days' => $data->anonymize_after_days,
            'ip_allowlist_mode' => $data->ip_allowlist_mode,
            'ip_allowlist' => $data->ip_allowlist,
            'geo_mode' => $data->geo_mode,
            'geo_countries' => $data->geo_countries,
            'webhook_url' => $data->webhook_url,
            'webhook_enabled' => $data->webhook_enabled,
            'webhook_events' => $data->webhook_events,
            'scim_enabled' => $data->scim_enabled,
        ];

        // A new secret rotates the stored one; null leaves it untouched.
        $secretRotated = $data->webhook_secret !== null && $data->webhook_secret !== '';
        if ($secretRotated) {
            $attributes['webhook_secret'] = $data->webhook_secret;
        }

        $policy->fill($attributes);
        $changed = $policy->getDirty();
        $policy->save();

        $this->emitAuditEvents($workspace, $actor, $original, $changed, $secretRotated);

        return $policy;
    }

    /**
     * One event per changed category, each carrying only its own before/after
     * diff. The webhook secret never appears in metadata — only a rotation flag.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $changed
     */
    private function emitAuditEvents(User $workspace, SecurityActorData $actor, array $original, array $changed, bool $secretRotated): void
    {
        $retentionDiff = $this->diffFor($original, $changed, self::RETENTION_FIELDS);
        if ($retentionDiff !== []) {
            $this->dispatch($workspace, $actor, SecurityAuditAction::PolicyRetentionChanged, $retentionDiff);
        }

        $networkDiff = $this->diffFor($original, $changed, self::NETWORK_FIELDS);
        if ($networkDiff !== []) {
            $this->dispatch($workspace, $actor, SecurityAuditAction::PolicyIpAllowlistChanged, $networkDiff);
        }

        $otherFields = ['webhook_url', 'webhook_enabled', 'webhook_events', 'scim_enabled'];
        $otherDiff = $this->diffFor($original, $changed, $otherFields);
        if ($secretRotated) {
            $otherDiff['webhook_secret_rotated'] = true;
        }
        if ($otherDiff !== []) {
            $this->dispatch($workspace, $actor, SecurityAuditAction::PolicyChanged, $otherDiff);
        }
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $changed
     * @param  array<int, string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffFor(array $original, array $changed, array $fields): array
    {
        $diff = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $changed)) {
                $diff[$field] = ['from' => $original[$field] ?? null, 'to' => $changed[$field]];
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function dispatch(User $workspace, SecurityActorData $actor, SecurityAuditAction $action, array $metadata): void
    {
        event(SecurityEventOccurred::for(
            action: $action,
            workspaceId: $workspace->id,
            actor: $actor,
            targetType: 'security_policy',
            targetId: $workspace->id,
            metadata: $metadata,
        ));
    }

    private function guardWebhookUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            throw UnsafeWebhookUrlException::for('The webhook URL must be a valid https URL.');
        }

        $host = trim($parts['host'], '[]'); // strip IPv6 literal brackets

        if (strtolower($host) === 'localhost') {
            throw UnsafeWebhookUrlException::for('The webhook URL must not point to an internal address.');
        }

        foreach ($this->resolveHost($host) as $ip) {
            if (IpUtils::checkIp($ip, self::BLOCKED_RANGES)) {
                throw UnsafeWebhookUrlException::for('The webhook URL must not point to an internal address.');
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = gethostbynamel($host);

        return $ips === false ? [] : $ips;
    }
}
