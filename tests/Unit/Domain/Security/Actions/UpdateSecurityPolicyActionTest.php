<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\UpdateSecurityPolicyAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\SecurityPolicyFormData;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;

/**
 * Saving Settings → Security: the form's settings are stored (the webhook
 * secret encrypted, the settings the form does not own kept), one audit
 * event per changed group without the secret, and a webhook URL that is not
 * public https refused.
 */
/** The person saving the form, as the audit log records them. */
function uspActor(User $user): SecurityActorData
{
    return SecurityActorData::forUser($user, '203.0.113.7', 'TestAgent/1.0');
}

beforeEach(function (): void {
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['93.184.216.34']);
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

it('stores the form settings with the secret encrypted, keeps the ones it does not own, and audits each group without the secret', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->update([
        'webhook_events' => ['auth.login'],
        'is_scim_enabled' => true,
    ]);

    app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(
            retention_conversations_days: 60,
            retention_audit_days: 120,
            ip_allowlist_mode: IpAllowlistMode::Dashboard,
            ip_allowlist: ['203.0.113.0/24', '198.51.100.7'],
            geo_mode: GeoMode::Allow,
            geo_countries: ['ae', 'sa'],
            webhook_url: 'https://hooks.example.com/siem',
            webhook_secret: 'shh-secret',
            webhook_enabled: true,
        ),
        uspActor($user),
    );

    $fresh = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);
    $stored = DB::table('workspace_security_policies')->where('workspace_id', $user->workspace()->id)->value('webhook_secret');

    expect($fresh->retention_conversations_days)->toBe(60)
        ->and($fresh->retention_audit_days)->toBe(120)
        ->and($fresh->ip_allowlist_mode)->toBe(IpAllowlistMode::Dashboard)
        ->and($fresh->ip_allowlist)->toBe(['203.0.113.0/24', '198.51.100.7'])
        ->and($fresh->geo_mode)->toBe(GeoMode::Allow)
        ->and($fresh->geo_countries)->toBe(['ae', 'sa'])
        ->and($fresh->is_webhook_enabled)->toBeTrue()
        ->and($fresh->webhook_secret)->toBe('shh-secret')
        ->and($fresh->webhook_events)->toBe(['auth.login'])
        ->and($fresh->is_scim_enabled)->toBeTrue()
        ->and($stored)->not->toContain('shh-secret')
        ->and(Crypt::decryptString($stored))->toBe('shh-secret');
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyRetentionChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyIpAllowlistChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyChanged->value
        && Str::contains($e->metadata, 'webhook_secret_rotated')
        && ! Str::contains($e->metadata, 'shh-secret'));
});

it('saves the lead, analytics and anonymize windows with one retention audit event, and clears them when sent blank', function (): void {
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->update(['retention_leads_days' => 90]);
    Event::fake([SecurityEventOccurred::class]);

    app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(retention_leads_days: 30, retention_analytics_days: 180, anonymize_after_days: 14),
        uspActor($user),
    );
    $saved = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);
    app(UpdateSecurityPolicyAction::class)->execute($user->workspace(), new SecurityPolicyFormData, uspActor($user));
    $cleared = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);

    expect([$saved->retention_leads_days, $saved->retention_analytics_days, $saved->anonymize_after_days])->toBe([30, 180, 14])
        ->and([$cleared->retention_leads_days, $cleared->retention_analytics_days, $cleared->anonymize_after_days])->toBe([null, null, null]);
    Event::assertDispatched(SecurityEventOccurred::class, function (SecurityEventOccurred $e): bool {
        $metadata = json_decode($e->metadata, true);

        return $e->action === SecurityAuditAction::PolicyRetentionChanged->value
            && $metadata['retention_leads_days'] === ['from' => 90, 'to' => 30]
            && $metadata['retention_analytics_days'] === ['from' => null, 'to' => 180]
            && $metadata['anonymize_after_days'] === ['from' => null, 'to' => 14];
    });
});

it('refuses a webhook URL that is not public https (SSRF guard)', function (string $url): void {
    $user = User::factory()->create();

    expect(fn () => app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(webhook_url: $url),
        uspActor($user),
    ))->toThrow(UnsafeWebhookUrlException::class);

    expect(WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->webhook_url)->toBeNull();
})->with([
    'plain http' => 'http://hooks.example.com/siem',
    'IPv4 loopback' => 'https://127.0.0.1/siem',
    'carrier-grade NAT' => 'https://100.64.0.1/siem',
    'IPv6 loopback' => 'https://[::1]/siem',
    'IPv6 unique-local' => 'https://[fd12::1]/siem',
    'IPv4-mapped IPv6' => 'https://[::ffff:10.0.0.1]/siem',
]);
