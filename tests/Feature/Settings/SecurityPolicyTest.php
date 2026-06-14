<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Models\WorkspaceSecurityPolicy;
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
| passes); non-Enterprise workspaces see an upsell teaser (entitled=false).
| Saving additionally requires the `security` entitlement (features:security),
| and the FormRequest's anti-lockout check rejects rules that would block the
| saver — pinned here against the default test IP (127.0.0.1).
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

function entitleSecurity(User $workspace): void
{
    WorkspaceSecurityPolicy::forWorkspace($workspace->id)->update(['security_override' => true]);
}

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

it('shows the network controls to an entitled workspace', function (): void {
    entitleSecurity($this->owner);

    $this->actingAs($this->owner)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('workspace/security/Security')
            ->where('entitled', true));
});

it('shows an upsell teaser to a non-entitled workspace', function (): void {
    $this->actingAs($this->owner)
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('workspace/security/Security')
            ->where('entitled', false));
});

it('403s a teammate without account.security', function (): void {
    $member = securityTeammate($this->owner, []);

    $this->actingAs($member)->get(route('security.edit'))->assertForbidden();
});

it('allows a teammate with account.security', function (): void {
    entitleSecurity($this->owner);
    $member = securityTeammate($this->owner, ['account.security']);

    $this->actingAs($member)->get(route('security.edit'))->assertOk();
});

it('persists network rules that keep the saver reachable', function (): void {
    entitleSecurity($this->owner);

    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['127.0.0.0/8', '203.0.113.0/24'],
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertSessionHasNoErrors();

    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->id);

    expect($policy->ip_allowlist_mode)->toBe('dashboard')
        ->and($policy->ip_allowlist)->toBe(['127.0.0.0/8', '203.0.113.0/24']);
});

it('rejects rules that would lock the saver out', function (): void {
    entitleSecurity($this->owner);

    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['10.0.0.0/8'], // excludes the test IP 127.0.0.1
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertSessionHasErrors('ip_allowlist');

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->id)->ip_allowlist_mode)->toBe('off');
});

it('rejects an invalid IP / CIDR entry', function (): void {
    entitleSecurity($this->owner);

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
    entitleSecurity($this->owner);

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

it('rejects conversation retention above the plan ceiling', function (): void {
    entitleSecurity($this->owner);

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
    entitleSecurity($this->owner);

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

function enterpriseOwner(): User
{
    $priceId = 'price_test_ent_'.Str::random(6);
    config(['billing.packages.enterprise.stripe_monthly_price_id' => $priceId]);

    $owner = User::factory()->create();
    $owner->billing()->firstOrCreate([]);
    $owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_'.Str::random(8),
        'stripe_status' => 'active',
        'stripe_price' => $priceId,
        'quantity' => 1,
    ]);
    $owner->refresh();
    app(TeamService::class)->createDefaultTeam($owner);

    return $owner;
}

it('lets an Enterprise workspace save a SIEM webhook', function (): void {
    Http::fake(); // the policy-change event may queue a (faked) delivery
    $owner = enterpriseOwner();

    $this->actingAs($owner)
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

    $policy = WorkspaceSecurityPolicy::forWorkspace($owner->id);

    expect($policy->webhook_enabled)->toBeTrue()
        ->and($policy->webhook_url)->toBe('https://siem.example.com/ingest')
        ->and($policy->webhook_secret)->toBe('super-secret'); // decrypted by the cast
});

it('ignores SIEM webhook fields without the SIEM capability', function (): void {
    entitleSecurity($this->owner); // security flag on (override), but starter plan → no siemWebhook capability

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

    expect(WorkspaceSecurityPolicy::forWorkspace($this->owner->id)->webhook_enabled)->toBeFalse();
});

it('rejects an internal SIEM webhook URL (SSRF)', function (): void {
    $owner = enterpriseOwner();

    $this->actingAs($owner)
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

it('blocks saving for a non-entitled workspace', function (): void {
    // Owner has account.security but the plan lacks the `security` entitlement.
    $this->actingAs($this->owner)
        ->put(route('security.update'), [
            'ip_allowlist_mode' => 'off',
            'ip_allowlist' => [],
            'geo_mode' => 'off',
            'geo_countries' => [],
        ])
        ->assertForbidden();
});
