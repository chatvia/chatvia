<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Workspace Security & Compliance — network access control (Phase 1)
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

function securityTeammate(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(10),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $member;
}

it('shows the security settings with the install retention as the ceiling', function (): void {
    config(['chatvia.retention.conversations_days' => 200]);

    $this->actingAs($this->owner)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('workspace/security/Security')
            ->missing('entitled')
            ->where('limits.conversations_max', 200)
            ->has('countries'));
});

it('403s a teammate without account.security', function (): void {
    $member = securityTeammate($this->owner, []);

    $this->actingAs($member)->get(route('security.edit'))->assertForbidden();
});

it('allows a teammate with account.security', function (): void {
    $member = securityTeammate($this->owner, ['account.security']);

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

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->id);

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

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->id)->fresh()->ip_allowlist_mode)->toBe(IpAllowlistMode::Off);
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
    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->id)->fresh()->geo_mode)->toBe(GeoMode::Off);
});

it('rejects a country rule that would lock the saver out while the IP allowlist is off', function (): void {
    // The gate enforces the country rule even when the IP allowlist is off, so
    // this save must be checked too (it used to be skipped).
    Cache::put('security:geoip:127.0.0.1', 'us', now()->addDay());

    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'deny',
            'geo_countries' => ['us'],
        ])
        ->assertSessionHasErrors('ip_allowlist');

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->id)->fresh()->geo_mode)->toBe(GeoMode::Off);
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

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->id);

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

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->id);

    expect($policy->webhook_enabled)->toBeTrue()
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
