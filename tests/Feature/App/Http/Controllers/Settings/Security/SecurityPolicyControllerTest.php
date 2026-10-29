<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Security\Enums\GeoMode;
use Domain\Security\Enums\IpAllowlistMode;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Facades\Http;

/**
 * The workspace's Security page (SecurityPolicyController): the network rules
 * and the SIEM webhook. The form request refuses rules that would lock the
 * saver out, by IP or by country, and an internal webhook address. Who may
 * open the page is in WorkspacePermissionRouteTest; the retention windows are
 * in SecurityRetentionTest.
 */
beforeEach(function (): void {
    withEnterpriseLicense();
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
});

/** A security form that changes only the given fields; every rule is off otherwise. */
function sppForm(array $fields = []): array
{
    return [
        'ip_allowlist_mode' => 'off',
        'ip_allowlist' => [],
        'geo_mode' => 'off',
        'geo_countries' => [],
        ...$fields,
    ];
}

/**
 * The saver's requests come from a public address the IP geolocation service
 * places in $country (the service is faked).
 */
function sppSaverIn(string $country): string
{
    config(['services.ip2location.key' => 'test-key']);
    Http::fake(['*' => Http::response(['country_code' => $country])]);

    return '8.8.8.8';
}

function sppPolicy(User $owner): WorkspaceSecurityPolicy
{
    return WorkspaceSecurityPolicy::forWorkspace($owner->workspace()->id)->fresh();
}

it('saves network rules that keep the saver reachable, refusing an IP allowlist that locks them out or an internal webhook', function (): void {
    $this->actingAs($this->owner)
        ->put(route('security.update'), sppForm([
            'ip_allowlist_mode' => 'dashboard',
            'ip_allowlist' => ['10.0.0.0/8'],
        ]))
        ->assertSessionHasErrors('ip_allowlist');

    expect(sppPolicy($this->owner)->ip_allowlist_mode)->toBe(IpAllowlistMode::Off);

    $this->put(route('security.update'), sppForm([
        'webhook_enabled' => true,
        'webhook_url' => 'https://127.0.0.1/ingest',
        'webhook_secret' => 'super-secret',
    ]))
        ->assertSessionHasErrors('webhook_url');

    expect(sppPolicy($this->owner)->webhook_url)->toBeNull();

    $this->put(route('security.update'), sppForm([
        'ip_allowlist_mode' => 'dashboard',
        'ip_allowlist' => ['127.0.0.0/8', '203.0.113.0/24'],
    ]))
        ->assertSessionHasNoErrors();

    expect(sppPolicy($this->owner))
        ->ip_allowlist_mode->toBe(IpAllowlistMode::Dashboard)
        ->ip_allowlist->toBe(['127.0.0.0/8', '203.0.113.0/24']);
});

it('rejects a country rule that would lock the saver out, and never blames the IP', function (string $ipMode, string $geoMode, array $countries): void {
    $ip = sppSaverIn('US');

    $this->actingAs($this->owner)
        ->withServerVariables(['REMOTE_ADDR' => $ip])
        ->put(route('security.update'), sppForm([
            'ip_allowlist_mode' => $ipMode,
            'geo_mode' => $geoMode,
            'geo_countries' => $countries,
        ]))
        ->assertSessionHasErrors('ip_allowlist');

    expect(session('errors')->first('ip_allowlist'))
        ->toContain('country')
        ->not->toContain($ip)
        ->not->toContain('network')
        ->and(sppPolicy($this->owner)->geo_mode)->toBe(GeoMode::Off);
})->with([
    'allow only other countries, no IP restriction' => ['dashboard', 'allow', ['ae']],
    "deny the saver's country, IP allowlist off" => ['off', 'deny', ['us']],
]);

it('refuses to save without an Enterprise license and keeps the saved rules', function (): void {
    $policy = WorkspaceSecurityPolicy::forWorkspace($this->owner->workspace()->id);
    $policy->update(['ip_allowlist_mode' => 'dashboard', 'ip_allowlist' => ['203.0.113.0/24']]);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    $this->actingAs($this->owner)
        ->put(route('security.update'), sppForm())
        ->assertForbidden();

    expect($policy->fresh()->ip_allowlist)->toBe(['203.0.113.0/24']);
});
