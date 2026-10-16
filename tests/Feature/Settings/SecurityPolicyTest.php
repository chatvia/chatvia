<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

/*
|--------------------------------------------------------------------------
| Workspace Security & Compliance — network access control
|--------------------------------------------------------------------------
|
| The page is gated by the `account.security` team permission (owner always
| passes), and the FormRequest's anti-lockout check rejects rules that would
| block the saver — pinned here against the default test IP (127.0.0.1).
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('shows the security settings with the install retention as the ceiling', function (): void {
    config(['chatvia.retention.conversations_days' => 200]);

    $this->actingAs($this->owner)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('workspace/security/Security')
            ->where('limits.conversations_max', 200)
            ->has('countries'));
});

it('403s a teammate without account.security', function (): void {
    $member = teammateWithPermissions($this->owner, []);

    $this->actingAs($member)->get(route('security.edit'))->assertForbidden();
});

it('allows a teammate with account.security', function (): void {
    $member = teammateWithPermissions($this->owner, ['account.security']);

    $this->actingAs($member)->get(route('security.edit'))->assertOk();
});

it('persists network rules that keep the saver reachable', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['127.0.0.0/8', '203.0.113.0/24'],
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);

    expect($policy->ip_allowlist_mode)->toBe(IpAllowlistMode::Dashboard)
        ->and($policy->ip_allowlist)->toBe(['127.0.0.0/8', '203.0.113.0/24']);
});

it('rejects rules that would lock the saver out', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['10.0.0.0/8'], // excludes the test IP 127.0.0.1
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertSessionHasErrors('ip_allowlist');

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->fresh()->ip_allowlist_mode)->toBe(IpAllowlistMode::Off);
});

it('rejects a country rule that would lock the saver out — and never blames the IP', function (): void {
    // countryFor() reads this cache before any HTTP geo lookup, so seeding it
    // pins the test IP (127.0.0.1) to a country the "allow only" rule excludes.
    Cache::put('security:geoip:127.0.0.1', 'us', now()->addDay());

    $response = $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => [], // no IP restriction at all
            'geo_mode' => 'allow',
            'geo_countries' => ['ae'], // excludes the saver's resolved country (US)
        ]);

    $response->assertSessionHasErrors('ip_allowlist');

    $message = session('errors')->get('ip_allowlist')[0];
    expect($message)->toContain('country')        // explains it's the country rule…
        ->and($message)->not->toContain('127.0.0.1') // …and never mentions the IP
        ->and($message)->not->toContain('network');

    // Nothing persisted — the save was rejected.
    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->fresh()->geo_mode)->toBe(GeoMode::Off);
});

it('rejects a country rule that would lock the saver out while the IP allowlist is off', function (): void {
    // The gate enforces the country rule even when the IP allowlist is off, so
    // this save must be checked too.
    Cache::put('security:geoip:127.0.0.1', 'us', now()->addDay());

    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'deny',
            'geo_countries' => ['us'],
        ])
        ->assertSessionHasErrors('ip_allowlist');

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->fresh()->geo_mode)->toBe(GeoMode::Off);
});

it('rejects an invalid IP / CIDR entry', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['not-an-ip'],
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertSessionHasErrors('ip_allowlist.0');
});

it('persists conversation + audit retention overrides', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
            'retention_conversations_days' => 30,
            'retention_audit_days' => 90,
        ])
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);

    expect($policy->retention_conversations_days)->toBe(30)
        ->and($policy->retention_audit_days)->toBe(90);
});

it('rejects conversation retention above the install retention', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
            'retention_conversations_days' => 99999,
            'retention_audit_days' => null,
        ])
        ->assertSessionHasErrors('retention_conversations_days');
});

it('rejects audit retention below the mandated floor', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
            'retention_conversations_days' => null,
            'retention_audit_days' => 5,
        ])
        ->assertSessionHasErrors('retention_audit_days');
});

it('lets a workspace save a SIEM webhook', function (): void {
    Http::fake(); // the policy-change event may queue a (faked) delivery

    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
            'webhook_enabled' => true,
            'webhook_url' => 'https://siem.example.com/ingest',
            'webhook_secret' => 'super-secret',
        ])
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);

    expect($policy->is_webhook_enabled)->toBeTrue()
        ->and($policy->webhook_url)->toBe('https://siem.example.com/ingest')
        ->and($policy->webhook_secret)->toBe('super-secret'); // decrypted by the cast
});

it('rejects an internal SIEM webhook URL (SSRF)', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
            'webhook_enabled' => true,
            'webhook_url' => 'https://127.0.0.1/ingest',
            'webhook_secret' => 'super-secret',
        ])
        ->assertSessionHasErrors('webhook_url');
});

/** A security form submission that changes only the given retention fields. */
function retentionForm(array $retention): array
{
    return [
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => [],
        'geo_mode' => 'off',
        'geo_countries' => [],
        ...$retention,
    ];
}

it('passes the lead, analytics and anonymize bounds to the page', function (): void {
    config([
        'chatvia.retention.leads_floor_days' => 1,
        'chatvia.retention.analytics_floor_days' => 30,
        'chatvia.retention.anonymize_floor_days' => 1,
        'chatvia.retention.max_days' => 3650,
    ]);

    $this->actingAs($this->owner)
        ->get(route('security.edit'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('limits.leads_floor', 1)
            ->where('limits.analytics_floor', 30)
            ->where('limits.anonymize_floor', 1)
            ->where('limits.max_days', 3650));
});

it('persists the lead, analytics and anonymize windows', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([
            'retention_conversations_days' => 90,
            'retention_leads_days' => 365,
            'retention_analytics_days' => 180,
            'anonymize_after_days' => 30,
        ]))
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);

    expect($policy->retention_leads_days)->toBe(365)
        ->and($policy->retention_analytics_days)->toBe(180)
        ->and($policy->anonymize_after_days)->toBe(30);
});

it('treats blank lead, analytics and anonymize windows as not set', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'retention_leads_days' => 90,
        'retention_analytics_days' => 90,
        'anonymize_after_days' => 30,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([
            'retention_leads_days' => '',
            'retention_analytics_days' => '',
            'anonymize_after_days' => '',
        ]))
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);

    expect($policy->retention_leads_days)->toBeNull()
        ->and($policy->retention_analytics_days)->toBeNull()
        ->and($policy->anonymize_after_days)->toBeNull();
});

it('rejects retention windows outside their floor and ceiling', function (string $field, int $days): void {
    config([
        'chatvia.retention.conversations_days' => 366,
        'chatvia.retention.leads_floor_days' => 1,
        'chatvia.retention.analytics_floor_days' => 30,
        'chatvia.retention.anonymize_floor_days' => 1,
        'chatvia.retention.max_days' => 3650,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([$field => $days]))
        ->assertSessionHasErrors($field);

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->fresh()->{$field})->toBeNull();
})->with([
    'leads below 1 day' => ['retention_leads_days', 0],
    'leads above the maximum' => ['retention_leads_days', 3651],
    'analytics below 30 days' => ['retention_analytics_days', 29],
    'analytics above the maximum' => ['retention_analytics_days', 3651],
    'anonymize below 1 day' => ['anonymize_after_days', 0],
    'anonymize above the install conversation retention' => ['anonymize_after_days', 367],
]);

it('accepts the edge values of each window', function (): void {
    config([
        'chatvia.retention.conversations_days' => 366,
        'chatvia.retention.analytics_floor_days' => 30,
        'chatvia.retention.max_days' => 3650,
    ]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([
            'retention_leads_days' => 3650,
            'retention_analytics_days' => 30,
            'anonymize_after_days' => 366,
        ]))
        ->assertSessionHasNoErrors();
});

it('rejects anonymizing later than the conversation retention sent with it', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([
            'retention_conversations_days' => 30,
            'anonymize_after_days' => 31,
        ]))
        ->assertSessionHasErrors('anonymize_after_days');

    $this->actingAs($this->owner)
        ->put(route('security.update'), retentionForm([
            'retention_conversations_days' => 30,
            'anonymize_after_days' => 30,
        ]))
        ->assertSessionHasNoErrors();
});
