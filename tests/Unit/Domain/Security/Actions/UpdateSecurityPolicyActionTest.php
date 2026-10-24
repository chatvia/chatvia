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

function uspActor(User $user): SecurityActorData
{
    return SecurityActorData::forUser($user, '203.0.113.7', 'TestAgent/1.0');
}

beforeEach(function (): void {
    // The webhook URL check looks the host up; answer with a public address
    // instead of asking real DNS.
    SafeUrl::resolveHostsUsing(fn (string $host): array => ['93.184.216.34']);
});

afterEach(function (): void {
    SafeUrl::resolveHostsUsing(null);
});

it('persists the form settings and encrypts the webhook secret', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

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

    expect((int) $fresh->workspace_id)->toBe($user->workspace()->id)
        ->and($fresh->retention_conversations_days)->toBe(60)
        ->and($fresh->retention_audit_days)->toBe(120)
        ->and($fresh->ip_allowlist_mode)->toBe(IpAllowlistMode::Dashboard)
        ->and($fresh->ip_allowlist)->toBe(['203.0.113.0/24', '198.51.100.7'])
        ->and($fresh->geo_mode)->toBe(GeoMode::Allow)
        ->and($fresh->geo_countries)->toBe(['ae', 'sa'])
        ->and($fresh->is_webhook_enabled)->toBeTrue()
        ->and($fresh->webhook_secret)->toBe('shh-secret');

    $stored = DB::table('workspace_security_policies')->where('workspace_id', $user->workspace()->id)->value('webhook_secret');

    expect($stored)->not->toContain('shh-secret')
        ->and(Crypt::decryptString($stored))->toBe('shh-secret');
});

it('keeps the settings the form does not own', function (): void {
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->update([
        'webhook_events' => ['auth.login'],
        'is_scim_enabled' => true,
    ]);

    app(UpdateSecurityPolicyAction::class)->execute($user->workspace(), new SecurityPolicyFormData(retention_audit_days: 200), uspActor($user));

    $fresh = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);

    expect($fresh->webhook_events)->toBe(['auth.login'])
        ->and($fresh->is_scim_enabled)->toBeTrue()
        ->and($fresh->retention_audit_days)->toBe(200);
});

it('saves the lead, analytics and anonymize windows with one retention audit event', function (): void {
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->update(['retention_leads_days' => 90]);
    Event::fake([SecurityEventOccurred::class]);

    app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(retention_leads_days: 30, retention_analytics_days: 180, anonymize_after_days: 14),
        uspActor($user),
    );

    $fresh = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);

    expect($fresh->retention_leads_days)->toBe(30)
        ->and($fresh->retention_analytics_days)->toBe(180)
        ->and($fresh->anonymize_after_days)->toBe(14);

    expect(Event::dispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyRetentionChanged->value))->toHaveCount(1);
    Event::assertDispatched(SecurityEventOccurred::class, function (SecurityEventOccurred $e): bool {
        $metadata = json_decode($e->metadata, true);

        return $e->action === SecurityAuditAction::PolicyRetentionChanged->value
            && $metadata['retention_leads_days'] === ['from' => 90, 'to' => 30]
            && $metadata['retention_analytics_days'] === ['from' => null, 'to' => 180]
            && $metadata['anonymize_after_days'] === ['from' => null, 'to' => 14];
    });
});

it('clears a window when the form sends it blank', function (): void {
    $user = User::factory()->create();
    WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->update([
        'retention_leads_days' => 90,
        'retention_analytics_days' => 60,
        'anonymize_after_days' => 30,
    ]);

    app(UpdateSecurityPolicyAction::class)->execute($user->workspace(), new SecurityPolicyFormData, uspActor($user));

    $fresh = WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id);

    expect($fresh->retention_leads_days)->toBeNull()
        ->and($fresh->retention_analytics_days)->toBeNull()
        ->and($fresh->anonymize_after_days)->toBeNull();
});

it('emits a category-specific audit event per changed group', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

    app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(
            retention_conversations_days: 30,
            ip_allowlist_mode: IpAllowlistMode::Dashboard,
            webhook_enabled: true,
        ),
        uspActor($user),
    );

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyRetentionChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyIpAllowlistChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyChanged->value);
});

it('never leaks the webhook secret into audit metadata, only a rotation flag', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

    app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(webhook_secret: 'shh-secret'),
        uspActor($user),
    );

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyChanged->value
        && Str::contains($e->metadata, 'webhook_secret_rotated')
        && ! Str::contains($e->metadata, 'shh-secret'));
});

it('rejects a non-https webhook URL (SSRF guard)', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(webhook_url: 'http://hooks.example.com/siem'),
        uspActor($user),
    ))->toThrow(UnsafeWebhookUrlException::class);
});

it('rejects a webhook URL pointing at an internal address (SSRF guard)', function (string $url): void {
    // The save-time check is strict in every environment, testing included.
    $user = User::factory()->create();

    expect(fn () => app(UpdateSecurityPolicyAction::class)->execute(
        $user->workspace(),
        new SecurityPolicyFormData(webhook_url: $url),
        uspActor($user),
    ))->toThrow(UnsafeWebhookUrlException::class);

    expect(WorkspaceSecurityPolicy::forWorkspace($user->workspace()->id)->webhook_url)->toBeNull();
})->with([
    'IPv4 loopback' => 'https://127.0.0.1/siem',
    'carrier-grade NAT' => 'https://100.64.0.1/siem',
    'IPv6 loopback' => 'https://[::1]/siem',
    'IPv6 unique-local' => 'https://[fd12::1]/siem',
    'IPv4-mapped IPv6' => 'https://[::ffff:10.0.0.1]/siem',
]);

it('resolves a single memoized policy through the user accessor', function (): void {
    $user = User::factory()->create();

    expect($user->securityPolicy())->toBeInstanceOf(WorkspaceSecurityPolicy::class)
        ->and($user->securityPolicy()->is($user->securityPolicy()))->toBeTrue();
});
