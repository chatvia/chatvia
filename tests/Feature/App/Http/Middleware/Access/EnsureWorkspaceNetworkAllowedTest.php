<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Analytics\Services\IP2Country;
use Domain\Membership\Team\Actions\Team\CreateDefaultTeamAction;
use Domain\Security\Models\WorkspaceSecurityPolicy;

/**
 * EnsureWorkspaceNetworkAllowed runs on every web request of a signed-in
 * person, and on every public API call made with a bearer token, and refuses
 * one from outside the workspace's network rules with a 403, before the page
 * is rendered. These tests drive it through Settings →
 * Security, a page the owner otherwise reaches. The decision itself is
 * NetworkAccessGuard's and NetworkRulesData's, tested on their own; this
 * file covers the wiring: the request's address, the visitor's country (from
 * Cloudflare when the peer is a Cloudflare edge), a workspace with no rules,
 * bearer-token API calls, no bypass for an admin, new rules applying on the
 * next request, and no enforcement without an Enterprise license.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    app(CreateDefaultTeamAction::class)->execute($this->owner);
});

/**
 * Geolocates the given addresses as these countries, and every other address
 * as unknown, instead of asking the geolocation service.
 *
 * @param  array<string, string>  $countries  address => ISO country code
 */
function ewnaLocate(array $countries): void
{
    app()->instance(IP2Country::class, new class($countries) extends IP2Country
    {
        /** @param  array<string, string>  $countries */
        public function __construct(private readonly array $countries) {}

        public function countryCode(string $ip): ?string
        {
            return $this->countries[$ip] ?? null;
        }
    });
}

it('applies the workspace rules to the request', function (array $policy, string $address, array $headers, array $countries, int $status): void {
    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update($policy);
    ewnaLocate($countries);

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => $address])
        ->withHeaders($headers)
        ->get(route('security.edit'))
        ->assertStatus($status);
})->with([
    'a workspace that never saved any rules' => [
        [], '198.51.100.7', [], [], 200,
    ],
    'an address outside the allowlist' => [
        ['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'off'], '198.51.100.7', [], [], 403,
    ],
    'an address inside the allowlist' => [
        ['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'off'], '203.0.113.9', [], [], 200,
    ],
    'an allowlist switched off' => [
        ['ip_allowlist_mode' => 'off', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'off'], '198.51.100.7', [], [], 200,
    ],
    'a country rule alone, from a country it does not allow' => [
        ['ip_allowlist_mode' => 'off', 'ip_allowlist' => [], 'geo_mode' => 'allow', 'geo_countries' => ['DK']], '198.51.100.7', [], ['198.51.100.7' => 'US'], 403,
    ],
    'a country rule beside an allowlist switched off that leaves the address out' => [
        ['ip_allowlist_mode' => 'off', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'allow', 'geo_countries' => ['DK']], '198.51.100.7', [], ['198.51.100.7' => 'DK'], 200,
    ],
    "Cloudflare's visitor country over the edge's" => [
        ['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => [], 'geo_mode' => 'deny', 'geo_countries' => ['BG']], '172.70.1.1', ['CF-IPCountry' => 'IQ'], ['172.70.1.1' => 'BG'], 200,
    ],
]);

it('applies new rules on the next request, to bearer-token API calls and to an admin like anyone else', function (): void {
    $rules = ['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'off'];
    $token = $this->owner->createToken('account')->plainTextToken;
    $admin = User::factory()->admin()->create();
    app(CreateDefaultTeamAction::class)->execute($admin);
    WorkspaceSecurityPolicy::forWorkspace($admin->workspace()->id)->update($rules);

    $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get(route('security.edit'))->assertForbidden();
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson('/api/v1/agents')->assertOk();

    WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id)->update($rules);

    $this->withToken($token)->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson('/api/v1/agents')->assertForbidden();
    $this->withToken($token)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/api/v1/agents')->assertOk();
});

it('does not enforce saved rules without an Enterprise license, and keeps them', function (): void {
    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);
    $policy->update(['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => ['203.0.113.0/24'], 'geo_mode' => 'off']);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->get(route('security.edit'))
        ->assertOk();

    expect($policy->fresh()->ip_allowlist)->toBe(['203.0.113.0/24']);
});
