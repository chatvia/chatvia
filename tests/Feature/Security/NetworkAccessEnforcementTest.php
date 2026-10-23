<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| EnsureWorkspaceNetworkAllowed — middleware body enforcement
|--------------------------------------------------------------------------
|
| The pure decision logic lives in NetworkAccessGuard::passes() and is tested
| in tests/Unit/Domain/Security/Services/NetworkAccessGuardTest.php. This file covers
| the MIDDLEWARE BODY that the unit test cannot reach: the admin bypass, the
| "no policy row / mode off" no-op, and the wiring that turns a request's real
| IP (+ resolved country) into a 403. It is appended to the `web` group
| (bootstrap/app.php), so it runs ahead of any route middleware and short-
| circuits the request before the page is ever rendered.
|
| `route('security.edit')` is the representative protected web route: the
| existing Settings\SecurityPolicyTest proves a plain owner reaches it with
| assertOk(), so a 403 here isolates the network gate rather than some
| unrelated dashboard gate. The geo branch is driven by priming
| the guard's geo-IP cache key (`security:geoip:{ip}`) — NetworkAccessGuard
| reads that cache before calling the real provider, so no live geo lookup runs.
*/

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
});

it('forbids an authenticated workspace whose IP is outside the allowlist', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => ['203.0.113.0/24'],
        'geo_mode' => 'off',
    ]);

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']) // outside the /24
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('lets the same workspace through from an allowlisted IP', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => ['203.0.113.0/24'],
        'geo_mode' => 'off',
    ]);

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']) // inside the /24
        ->get(route('security.edit'))
        ->assertOk();
});

it('enforces the own workspace policy on a platform admin like anyone else', function (): void {
    // Admins only reach their own workspace on the dashboard, so its network
    // rules apply to them too. Admins are a workspace type, so they pass the
    // page's own gates and only the network rule can refuse them.
    $admin = User::factory()->admin()->create();
    app(TeamService::class)->createDefaultTeam($admin);
    WorkspaceSecurityPolicy::forWorkspace($admin->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => ['10.0.0.0/8'],
        'geo_mode' => 'off',
    ]);

    $this->actingAs($admin)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('passes a workspace whose policy mode is off', function (): void {
    // The row exists with an allowlist the request IP is outside of, but the
    // mode is off, so the middleware must let the request through.
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => ['203.0.113.0/24'],
        'geo_mode' => 'off',
    ]);
    expect(WorkspaceSecurityPolicy::query()->where('workspace_id', $this->owner->workspace()->id)->exists())->toBeTrue();

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();
});

it('passes a workspace that has no policy row at all', function (): void {
    // A fresh owner with no WorkspaceSecurityPolicy row — the middleware's
    // `$policy === null` branch must fall straight through to the request.
    $fresh = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($fresh);

    expect(WorkspaceSecurityPolicy::query()->where('workspace_id', $fresh->workspace()->id)->exists())->toBeFalse();

    $this->actingAs($fresh)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();
});

it('forbids a request from a geo-denied country', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard', // mode must be on for the body to evaluate
        'ip_allowlist' => [],               // empty ⇒ no IP restriction; geo decides
        'geo_mode' => 'deny',
        'geo_countries' => ['RU'],
    ]);

    // Prime the guard's geo cache so the deterministic decision uses 'RU'
    // without a live geo-IP lookup.
    Cache::put('security:geoip:198.51.100.7', 'RU', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('passes a request from a country not on the deny list', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => [],
        'geo_mode' => 'deny',
        'geo_countries' => ['RU'],
    ]);

    Cache::put('security:geoip:198.51.100.7', 'US', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();
});

/*
| Geo-only policy: the IP allowlist is OFF and geo alone decides. This is the
| common UI path ("Allow only these → Denmark" with no IP rules), so the
| middleware must not skip the check just because `ip_allowlist_mode` is 'off'.
*/

it('forbids a geo-allow policy from a country not on the allow list, with the IP allowlist off', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'off', // the exact UI scenario: only geo is configured
        'ip_allowlist' => [],
        'geo_mode' => 'allow',
        'geo_countries' => ['DK'], // allow only Denmark
    ]);

    Cache::put('security:geoip:198.51.100.7', 'US', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('passes a geo-allow policy from an allow-listed country, with the IP allowlist off', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => [],
        'geo_mode' => 'allow',
        'geo_countries' => ['DK'],
    ]);

    Cache::put('security:geoip:198.51.100.7', 'DK', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();
});

it('ignores a dormant IP allowlist when its mode is off and only geo is active', function (): void {
    // A workspace that once configured IP rules, turned them OFF, then enabled
    // geo. The stale `ip_allowlist` must NOT be enforced — only geo decides — or
    // turning geo on would unexpectedly resurrect the old IP fence.
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => ['203.0.113.0/24'], // dormant: the request IP is outside it
        'geo_mode' => 'allow',
        'geo_countries' => ['DK'],
    ]);

    Cache::put('security:geoip:198.51.100.7', 'DK', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']) // outside the dormant /24
        ->get(route('security.edit'))
        ->assertOk();
});

/*
| Cloudflare's CF-IPCountry header (the REAL visitor's country) wins over a
| geo lookup of the connecting IP — which, behind CF, is the edge/egress IP and
| resolves to the serving PoP's country (e.g. Bulgaria for Middle-East traffic),
| not the visitor's. Both tests prime the IP geo cache to the WRONG (edge)
| country and assert the decision follows the header instead.
*/

it('trusts the Cloudflare visitor country over the connecting edge IP', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => [],
        'geo_mode' => 'deny',
        'geo_countries' => ['BG'], // block Bulgaria — the edge PoP's country
    ]);

    // The connecting IP geo-resolves to Bulgaria (the CF edge), but the visitor
    // is in Iraq: the header must let them through. The peer is a Cloudflare
    // edge IP (172.64.0.0/13), so the header counts as genuine.
    Cache::put('security:geoip:172.70.1.1', 'BG', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
        ->withHeaders(['CF-IPCountry' => 'IQ'])
        ->get(route('security.edit'))
        ->assertOk();
});

it('blocks on the Cloudflare visitor country even when the edge IP is elsewhere', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => [],
        'geo_mode' => 'deny',
        'geo_countries' => ['IQ'], // block Iraq — the real visitor
    ]);

    // Edge IP looks like Bulgaria, but CF reports the visitor is in Iraq.
    Cache::put('security:geoip:172.70.1.1', 'BG', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
        ->withHeaders(['CF-IPCountry' => 'IQ'])
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('falls back to the IP geo lookup when CF-IPCountry is a non-country sentinel', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => [],
        'geo_mode' => 'deny',
        'geo_countries' => ['RU'],
    ]);

    Cache::put('security:geoip:172.70.1.1', 'RU', now()->addDay());

    // XX = country undetermined ⇒ ignore the header, use the IP geo (RU ⇒ 403).
    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
        ->withHeaders(['CF-IPCountry' => 'XX'])
        ->get(route('security.edit'))
        ->assertForbidden();
});

it('ignores a spoofed CF-IPCountry from a peer that did not come through Cloudflare', function (): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => [],
        'geo_mode' => 'deny',
        'geo_countries' => ['RU'],
    ]);

    // The peer's own IP resolves to a blocked country while the header claims
    // a clean one. A direct connection cannot vouch for CF-* headers, so the
    // header is ignored and the peer's country decides: blocked.
    Cache::put('security:geoip:198.51.100.7', 'RU', now()->addDay());

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['CF-IPCountry' => 'US'])
        ->get(route('security.edit'))
        ->assertForbidden();
});
