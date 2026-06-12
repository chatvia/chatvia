<?php

use App\Identity\Workspace\Models\User;
use Domain\Security\Actions\UpdateSecurityPolicyAction;
use Domain\Security\Data\SecurityActorData;
use Domain\Security\Data\WorkspaceSecurityPolicyData;
use Domain\Security\Enums\SecurityAuditAction;
use Domain\Security\Events\SecurityEventOccurred;
use Domain\Security\Exceptions\UnsafeWebhookUrlException;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Event;

function policyActor(User $user): SecurityActorData
{
    return SecurityActorData::forUser($user, '203.0.113.7', 'TestAgent/1.0');
}

it('persists the workspace policy and encrypts the webhook secret', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

    $policy = app(UpdateSecurityPolicyAction::class)->execute(
        $user,
        new WorkspaceSecurityPolicyData(
            retention_conversations_days: 60,
            retention_audit_days: 120,
            ip_allowlist_mode: 'dashboard',
            geo_mode: 'allow',
            geo_countries: ['AE', 'SA'],
            webhook_url: 'https://hooks.example.com/siem',
            webhook_secret: 'shh-secret',
            webhook_enabled: true,
            scim_enabled: true,
        ),
        policyActor($user),
    );

    $fresh = WorkspaceSecurityPolicy::forWorkspace($user->id);

    expect((int) $fresh->workspace_id)->toBe($user->id)
        ->and($fresh->retention_conversations_days)->toBe(60)
        ->and($fresh->retention_audit_days)->toBe(120)
        ->and($fresh->ip_allowlist_mode)->toBe('dashboard')
        ->and($fresh->geo_mode)->toBe('allow')
        ->and($fresh->geo_countries)->toBe(['AE', 'SA'])
        ->and($fresh->webhook_enabled)->toBeTrue()
        ->and($fresh->scim_enabled)->toBeTrue()
        ->and($fresh->webhook_secret)->toBe('shh-secret'); // decrypted by the cast
});

it('emits a category-specific audit event per changed group', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

    app(UpdateSecurityPolicyAction::class)->execute(
        $user,
        new WorkspaceSecurityPolicyData(
            retention_conversations_days: 30,
            ip_allowlist_mode: 'dashboard',
            scim_enabled: true,
        ),
        policyActor($user),
    );

    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyRetentionChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyIpAllowlistChanged->value);
    Event::assertDispatched(SecurityEventOccurred::class, fn (SecurityEventOccurred $e): bool => $e->action === SecurityAuditAction::PolicyChanged->value);
});

it('never leaks the webhook secret into audit metadata, only a rotation flag', function (): void {
    Event::fake([SecurityEventOccurred::class]);
    $user = User::factory()->create();

    app(UpdateSecurityPolicyAction::class)->execute(
        $user,
        new WorkspaceSecurityPolicyData(webhook_secret: 'shh-secret'),
        policyActor($user),
    );

    Event::assertDispatched(SecurityEventOccurred::class, function (SecurityEventOccurred $e): bool {
        return $e->action === SecurityAuditAction::PolicyChanged->value
            && str_contains($e->metadata, 'webhook_secret_rotated')
            && ! str_contains($e->metadata, 'shh-secret');
    });
});

it('rejects a non-https webhook URL (SSRF guard)', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(UpdateSecurityPolicyAction::class)->execute(
        $user,
        new WorkspaceSecurityPolicyData(webhook_url: 'http://hooks.example.com/siem'),
        policyActor($user),
    ))->toThrow(UnsafeWebhookUrlException::class);
});

it('rejects a webhook URL pointing at an internal address (SSRF guard)', function (): void {
    $user = User::factory()->create();

    expect(fn () => app(UpdateSecurityPolicyAction::class)->execute(
        $user,
        new WorkspaceSecurityPolicyData(webhook_url: 'https://127.0.0.1/siem'),
        policyActor($user),
    ))->toThrow(UnsafeWebhookUrlException::class);
});

it('resolves a single memoized policy through the user accessor', function (): void {
    $user = User::factory()->create();

    expect($user->securityPolicy())->toBeInstanceOf(WorkspaceSecurityPolicy::class)
        ->and($user->securityPolicy()->is($user->securityPolicy()))->toBeTrue();
});
