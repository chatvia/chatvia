<?php

namespace Domain\Security\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\SecurityPolicyFormData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Support\Http\SafeUrl;

/**
 * The write path of the Security settings page for the keystone
 * {@see WorkspaceSecurityPolicy}. It applies what the form owns — the network
 * rules, conversation/audit retention and the SIEM webhook — and keeps every other stored setting (leads/analytics/anonymise
 * retention, the webhook event list, SCIM). It guards the SIEM webhook URL
 * against SSRF, saves, and emits one audit event per changed category
 * (network / retention / other) so each surfaces under its specific
 * {@see SecurityAuditAction} with a before/after diff.
 *
 * The `webhook_secret` is write-only: a non-null value rotates it, null leaves
 * the stored secret untouched, and its value is NEVER placed in audit metadata.
 *
 * Request-free per the layering rules — the controller resolves the acting user
 * + live request IP into {@see SecurityActorData} and passes it in.
 */
class UpdateSecurityPolicyAction
{
    /** @var list<string> */
    private const array RETENTION_FIELDS = [
        'retention_conversations_days', 'retention_leads_days', 'retention_audit_days',
        'retention_analytics_days', 'anonymize_after_days',
    ];

    /** @var list<string> */
    private const array NETWORK_FIELDS = ['ip_allowlist_mode', 'ip_allowlist', 'geo_mode', 'geo_countries'];

    /** @var list<string> */
    private const array OTHER_FIELDS = ['webhook_url', 'webhook_enabled', 'webhook_events', 'scim_enabled'];

    /**
     * @throws UnsafeWebhookUrlException when the webhook URL is not a public https URL
     */
    public function execute(User $workspace, SecurityPolicyFormData $form, SecurityActorData $actor): WorkspaceSecurityPolicy
    {
        $this->guardWebhookUrl($form->webhook_url);

        $policy = WorkspaceSecurityPolicy::forWorkspace($workspace->id);
        $original = $policy->getOriginal();

        $policy->fill($this->attributes($form));
        $changed = $policy->getDirty();
        $policy->save();

        $this->emitAuditEvents($workspace, $actor, $original, $changed, $form->rotatesWebhookSecret());

        return $policy;
    }

    /**
     * The policy columns the form may change. The webhook secret changes only
     * when a new one is given.
     *
     * @return array<string, mixed>
     */
    private function attributes(SecurityPolicyFormData $form): array
    {
        $attributes = [
            'retention_conversations_days' => $form->retention_conversations_days,
            'retention_audit_days' => $form->retention_audit_days,
            'ip_allowlist_mode' => $form->ip_allowlist_mode,
            'ip_allowlist' => $form->ip_allowlist,
            'geo_mode' => $form->geo_mode,
            'geo_countries' => $form->geo_countries,
            'webhook_url' => $form->webhook_url,
            'webhook_enabled' => $form->webhook_enabled,
        ];

        if ($form->rotatesWebhookSecret()) {
            $attributes['webhook_secret'] = $form->webhook_secret;
        }

        return $attributes;
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

        $otherDiff = $this->diffFor($original, $changed, self::OTHER_FIELDS);
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

    /**
     * Save-time SSRF check with the same address rules as every other outbound
     * URL ({@see SafeUrl}: A and AAAA records, only globally routable
     * addresses). It runs in every environment, since a SIEM endpoint is never
     * a local mock server, and the webhook must be https because it carries
     * security events. A host that does not resolve yet is accepted: delivery
     * re-checks the URL before every send, since DNS can change after saving.
     */
    private function guardWebhookUrl(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
            throw UnsafeWebhookUrlException::for('The webhook URL must be a valid https URL.');
        }

        $host = trim((string) $parts['host'], '[]');

        if (strtolower($host) === 'localhost') {
            throw UnsafeWebhookUrlException::for('The webhook URL must not point to an internal address.');
        }

        foreach (SafeUrl::resolve($host) as $ip) {
            if (! SafeUrl::isPublicIp($ip)) {
                throw UnsafeWebhookUrlException::for('The webhook URL must not point to an internal address.');
            }
        }
    }
}
